# Native Libraries

Official releases vendor prebuilt `cloudflare/quiche` shared libraries in this directory so that `composer require 18230/php-tuic-client` can work without a second manual download step.

Current bundled triplets:

- `windows-x64/quiche.dll`
- `linux-x64/libquiche.so`
- `macos-x64/libquiche.dylib`

At runtime the resolver checks:

1. `QUICHE_LIB` / `--quiche-lib`
2. `resources/native/<platform>-<arch>/...`
3. `resources/native/<platform>/...`

If you build your own library for another architecture, place it under the matching triplet directory or point `QUICHE_LIB` to the absolute path.
