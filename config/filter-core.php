<?php

use Illuminate\Foundation\Auth\User;

// config for Ameax/FilterCore
return [

    /*
    |--------------------------------------------------------------------------
    | User Model
    |--------------------------------------------------------------------------
    |
    | The user model to use for FilterPreset ownership.
    | Change this if you use a custom user model.
    |
    */

    'user_model' => User::class,

    /*
    |--------------------------------------------------------------------------
    | Timezone
    |--------------------------------------------------------------------------
    |
    | The timezone used for date/datetime filter queries.
    | When a user filters for "today" in Europe/Berlin, the query needs to
    | convert this to UTC for the database.
    |
    | Set to null to use the application timezone (config('app.timezone')).
    |
    */

    'timezone' => null,

];
