# Configuration

If :package_name publishes a configuration file, publish it with:

```bash
php artisan vendor:publish --tag=":package_slug-config"
```

Document the generated configuration file here. Explain each option, its default value, and when an application should change it.

```php
<?php

return [
    // 'option' => 'value',
];
```

If :package_name does not publish configuration, replace this page with any required environment variables or installation checks, or remove it from the sidebar.
