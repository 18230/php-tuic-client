param(
    [string] $Repository = '',
    [string] $Username = '',
    [string] $ApiToken = '',
    [switch] $Force
)

$ErrorActionPreference = 'Stop'

function Get-GitHubCliPath {
    $gh = Get-Command gh -ErrorAction SilentlyContinue
    if ($gh) {
        return $gh.Source
    }

    $fallback = 'C:\Program Files\GitHub CLI\gh.exe'
    if (Test-Path $fallback) {
        return $fallback
    }

    throw 'GitHub CLI (gh) was not found. Install gh and authenticate before running this script.'
}

function Invoke-GitHubApi {
    param(
        [string] $Executable,
        [string] $Path,
        [string] $Method = 'GET',
        [string] $InputJson = ''
    )

    if ([string]::IsNullOrWhiteSpace($InputJson)) {
        $result = & $Executable api $Path --method $Method
    } else {
        $result = $InputJson | & $Executable api $Path --method $Method --input -
    }

    if ($LASTEXITCODE -ne 0) {
        throw 'GitHub CLI request failed. Make sure gh is authenticated with the admin:repo_hook scope.'
    }

    return $result
}

function Resolve-Repository {
    param([string] $CurrentValue)

    if (-not [string]::IsNullOrWhiteSpace($CurrentValue)) {
        return $CurrentValue.Trim()
    }

    $remote = git remote get-url origin 2>$null
    if ([string]::IsNullOrWhiteSpace($remote)) {
        throw 'Repository was not provided and origin remote could not be resolved.'
    }

    $remote = $remote.Trim()

    if ($remote -match 'github\.com[:/](?<repo>[^/]+/[^/.]+?)(?:\.git)?$') {
        return $Matches['repo']
    }

    throw "Unable to parse GitHub repository slug from remote: $remote"
}

function Resolve-Username {
    param(
        [string] $CurrentValue,
        [string] $RepositorySlug
    )

    if (-not [string]::IsNullOrWhiteSpace($CurrentValue)) {
        return $CurrentValue.Trim()
    }

    if (-not [string]::IsNullOrWhiteSpace($env:PACKAGIST_USERNAME)) {
        return $env:PACKAGIST_USERNAME.Trim()
    }

    return $RepositorySlug.Split('/')[0]
}

function Resolve-ApiToken {
    param([string] $CurrentValue)

    if (-not [string]::IsNullOrWhiteSpace($CurrentValue)) {
        return $CurrentValue.Trim()
    }

    if (-not [string]::IsNullOrWhiteSpace($env:PACKAGIST_API_TOKEN)) {
        return $env:PACKAGIST_API_TOKEN.Trim()
    }

    throw 'Packagist API token is required. Pass -ApiToken or set PACKAGIST_API_TOKEN.'
}

$gh = Get-GitHubCliPath
$Repository = Resolve-Repository -CurrentValue $Repository
$Username = Resolve-Username -CurrentValue $Username -RepositorySlug $Repository
$ApiToken = Resolve-ApiToken -CurrentValue $ApiToken
$payloadUrl = "https://packagist.org/api/github?username=$Username"

$hooks = Invoke-GitHubApi -Executable $gh -Path "repos/$Repository/hooks" | ConvertFrom-Json
$existing = $hooks | Where-Object { $_.config.url -eq $payloadUrl } | Select-Object -First 1

$body = @{
    active = $true
    events = @('push')
    config = @{
        url = $payloadUrl
        content_type = 'json'
        secret = $ApiToken
        insecure_ssl = '0'
    }
}

if (-not $existing) {
    $body.name = 'web'
}

$json = $body | ConvertTo-Json -Depth 6 -Compress

if ($existing -and -not $Force) {
    Write-Output "Packagist webhook already exists for $Repository"
    Write-Output 'Use -Force if you want to update the stored Packagist API token.'
    exit 0
}

if ($existing) {
    $result = Invoke-GitHubApi -Executable $gh -Path "repos/$Repository/hooks/$($existing.id)" -Method 'PATCH' -InputJson $json
    $action = 'updated'
} else {
    $result = Invoke-GitHubApi -Executable $gh -Path "repos/$Repository/hooks" -Method 'POST' -InputJson $json
    $action = 'created'
}

$hook = $result | ConvertFrom-Json
Write-Output "Packagist webhook $action for $Repository"
Write-Output "Payload URL: $payloadUrl"
Write-Output "Hook ID: $($hook.id)"
