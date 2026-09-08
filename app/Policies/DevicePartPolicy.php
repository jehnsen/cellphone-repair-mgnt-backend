<?php

namespace App\Policies;

use App\Policies\Concerns\AuthorizesCatalog;

/**
 * The rig is reference data like any other catalog: everyone at the bench
 * reads it, only catalog.manage edits it. No delete of consequence — a part
 * a stored snapshot names has to keep resolving, so the controller
 * deactivates rather than removing.
 */
class DevicePartPolicy
{
    use AuthorizesCatalog;
}
