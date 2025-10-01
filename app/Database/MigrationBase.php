<?php

namespace App\Database;

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\Forge;
use CodeIgniter\Database\Migration;

/**
 * Shared migration base to provide typed properties for IDEs and static analysers.
 *
 * @property BaseConnection $db
 * @property Forge          $forge
 */
abstract class MigrationBase extends Migration
{
    /** @var BaseConnection */
    protected $db;

    /** @var Forge */
    protected $forge;
}
