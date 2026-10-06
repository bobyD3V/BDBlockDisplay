<?php
declare(strict_types=1);

namespace BDBlockDisplay\BobyDev\Core;

/**
 * Stable numeric item identifiers used by the Customies-backed editor tools.
 * Keeping these IDs in one class prevents registration services from depending
 * on implementation details of ToolService.
 */
final class ToolIdRegistry{
    public const WAND = 30001;
    public const ROTATE = 30002;
    public const SCALE = 30003;
    public const GROUP = 30004;

    private function __construct(){
    }
}
