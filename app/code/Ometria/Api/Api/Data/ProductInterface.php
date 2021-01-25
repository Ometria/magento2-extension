<?php

namespace Ometria\Api\Api\Data;

interface ProductInterface
{
    const TYPE = "@type";
    const ID = "id";
    const SKU = "sku";
    const TITLE = "title";
    const PRICE = "price";
    const SPECIAL_PRICE = "special_price";
    const FINAL_PRICE = "final_price";
    const FINAL_PRICE_INCL_TAX = "final_price_incl_tax";
    const TAX_AMOUNT = "tax";
    const URL = "url";
    const IMAGE_URL = "image_url";
    const IS_VARIANT = "is_variant";
    const PARENT_ID = "parent_id";
    const IS_ACTIVE = "is_active";
    const IS_IN_STOCK = "is_in_stock";
    const QTY = "qty";
    const STORES = "stores";
    const ATTRIBUTES = "attributes";
    const RAW = "_raw";
    const STORE_LISTINGS = "store_listings";
    const STATUS = 'status';
    const VISIBILITY = 'visibility';
    const STORE_ID = 'store_id';
    const STORE_CURRENCY = 'store_currency';
}
