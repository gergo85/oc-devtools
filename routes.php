<?php

Route::get('/phpinfo', function()
{
    $admin = BackendAuth::getUser();

    if (isset($admin) && $admin->is_superuser == 1) {
        echo phpinfo();
    }
});
