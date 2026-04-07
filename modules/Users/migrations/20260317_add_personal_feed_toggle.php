<?php
return new class {
    public function up(\Core\Database $db): void
    {
        // Personal feed fields are intentionally not used in SteelRoot.
    }

    public function down(\Core\Database $db): void
    {
        // No-op.
    }
};
