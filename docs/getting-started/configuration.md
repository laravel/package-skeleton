# Configuration

You may publish the configuration file with:

```bash
php artisan vendor:publish --tag=":package_slug-config"
```

This will publish a `:package_slug.php` configuration file in your application's `config` directory.

<!-- Document each configuration option, its default value, and when to change it. -->

```php
<?php

return [
    // 'option' => 'value',
];
```
