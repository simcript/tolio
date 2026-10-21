<?php
namespace Src\Modules\Kalaqpar\Services;

use Src\Modules\Kalaqpar\Database\Connection;

final class Factory
{
    public readonly Connection $db;
    public readonly array $settings;
    public function __construct()
    {
        $this->settings = Settings::values();
        $this->db = new Connection((string) $this->settings['database']);
    }
    public function engine(): Engine { return new Engine($this->db, $this->settings); }
    public function state(): State { return new State($this->db, $this->settings); }
    public function identity(): Identity { return new Identity($this->db, $this->settings); }
}
