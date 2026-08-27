<?php

return [
    // Hard cap on the number of questions one municipality can configure.
    // Deliberately not an env value: the app is multi-tenant with a single
    // deploy, so env could never make this differ per municipality. If it
    // ever needs to, add a column on `municipalities` instead.
    'max_per_municipality' => 15,
];
