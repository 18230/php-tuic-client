<?php declare(strict_types=1);

namespace PhpTuic\Runtime;

use InvalidArgumentException;

final readonly class IpAccessList
{
    /**
     * @param list<array{type: 'exact'|'cidr', binary: string, bits?: int, raw: string}> $rules
     */
    private function __construct(
        private array $rules,
    ) {
    }

    /**
     * @param list<string> $entries
     */
    public static function fromStrings(array $entries): self
    {
        $rules = [];

        foreach ($entries as $entry) {
            $entry = trim($entry);
            if ($entry === '') {
                continue;
            }

            $rules[] = self::parseRule($entry);
        }

        return new self($rules);
    }

    public function allows(?string $ip): bool
    {
        if ($this->rules === []) {
            return true;
        }

        if ($ip === null || $ip === '') {
            return false;
        }

        $binaryIp = @inet_pton($ip);
        if ($binaryIp === false) {
            return false;
        }

        foreach ($this->rules as $rule) {
            if (strlen($binaryIp) !== strlen($rule['binary'])) {
                continue;
            }

            if ($rule['type'] === 'exact' && hash_equals($rule['binary'], $binaryIp)) {
                return true;
            }

            if ($rule['type'] === 'cidr' && self::matchesCidr($binaryIp, $rule['binary'], (int) $rule['bits'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    public function entries(): array
    {
        return array_map(static fn (array $rule): string => $rule['raw'], $this->rules);
    }

    /**
     * @return array{type: 'exact'|'cidr', binary: string, bits?: int, raw: string}
     */
    private static function parseRule(string $entry): array
    {
        if (!str_contains($entry, '/')) {
            $binary = @inet_pton($entry);
            if ($binary === false) {
                throw new InvalidArgumentException(sprintf('Invalid allow-ip value "%s".', $entry));
            }

            return [
                'type' => 'exact',
                'binary' => $binary,
                'raw' => $entry,
            ];
        }

        [$network, $bits] = explode('/', $entry, 2);
        $binaryNetwork = @inet_pton(trim($network));
        if ($binaryNetwork === false || trim($bits) === '' || !ctype_digit(trim($bits))) {
            throw new InvalidArgumentException(sprintf('Invalid CIDR allow-ip value "%s".', $entry));
        }

        $bitCount = (int) $bits;
        $maxBits = strlen($binaryNetwork) * 8;
        if ($bitCount < 0 || $bitCount > $maxBits) {
            throw new InvalidArgumentException(sprintf('CIDR prefix out of range in "%s".', $entry));
        }

        return [
            'type' => 'cidr',
            'binary' => $binaryNetwork,
            'bits' => $bitCount,
            'raw' => sprintf('%s/%d', trim($network), $bitCount),
        ];
    }

    private static function matchesCidr(string $ip, string $network, int $bits): bool
    {
        if ($bits === 0) {
            return true;
        }

        $fullBytes = intdiv($bits, 8);
        $remainingBits = $bits % 8;

        if ($fullBytes > 0 && substr($ip, 0, $fullBytes) !== substr($network, 0, $fullBytes)) {
            return false;
        }

        if ($remainingBits === 0) {
            return true;
        }

        $mask = (0xFF << (8 - $remainingBits)) & 0xFF;

        return (ord($ip[$fullBytes]) & $mask) === (ord($network[$fullBytes]) & $mask);
    }
}
