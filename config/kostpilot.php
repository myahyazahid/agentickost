<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Identity Hash Key
    |--------------------------------------------------------------------------
    |
    | Key for the HMAC of residents' identity numbers (schema §1.9). The
    | numbers themselves are encrypted with APP_KEY; this separate key lets
    | duplicates be found without decrypting. Generate with:
    | php -r "echo base64_encode(random_bytes(32)), PHP_EOL;"
    |
    */

    'identity_hash_key' => env('IDENTITY_HASH_KEY'),

];
