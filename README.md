# flareia/laravel-sdk

SDK Laravel para reportar exceptions, performance e logs ao [FlareIA](https://flareia.com.br).

## Instalar

```bash
composer require flareia/laravel-sdk
php artisan vendor:publish --tag=flareia-config
```

## Registar o handler

Laravel 11+ em `bootstrap/app.php`:

```php
->withExceptions(function (Exceptions $exceptions) {
    \FlareIA\Sdk\Laravel\FlareIA::handles($exceptions);
})
```

## Verificar

```bash
php artisan flareia:test
```

Defina `FLAREIA_API_KEY` no `.env`.

Documentação: [flareia.com.br/docs/sdks/laravel](https://flareia.com.br/docs/sdks/laravel).

## Desenvolvimento

```bash
composer config repositories.flareia-php path ../flareia-php-sdk
composer install
composer test
```

Remova o repositório `path` antes de publicar no Packagist; em produção use `flareia/php-sdk` do registry.
