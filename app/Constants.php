<?php

/*
|--------------------------------------------------------------------------
| Application Constants
|--------------------------------------------------------------------------
|
| This file contains real PHP constants that are loaded during application
| bootstrap. These are immutable values that should not change at runtime.
|
*/

/*
|--------------------------------------------------------------------------
| Billing Constants
|--------------------------------------------------------------------------
*/

// Number of orders required to trigger automatic subscription creation
define('QTD_PEDIDOS_COBRAR', 30);

/*
|--------------------------------------------------------------------------
| Pagination Constants
|--------------------------------------------------------------------------
*/

// Default pagination limit for product listings
define('PRODUTOS_POR_PAGINA', 15);

// Default pagination limit for order listings
define('PEDIDOS_POR_PAGINA', 15);

/*
|--------------------------------------------------------------------------
| File Upload Constants
|--------------------------------------------------------------------------
*/

// Maximum file size for uploads (in MB)
define('UPLOAD_MAX_SIZE_MB', 15);

// Allowed image formats
define('ALLOWED_IMAGE_FORMATS', ['jpeg', 'jpg', 'png', 'gif', 'webp']);

/*
|--------------------------------------------------------------------------
| Business Logic Constants
|--------------------------------------------------------------------------
*/

// Days to wait before deactivating unpaid subscriptions
define('DIAS_PARA_DESATIVAR_ASSINATURA', 5);

// Minimum order value for delivery (in cents)
define('VALOR_MINIMO_ENTREGA', 0);