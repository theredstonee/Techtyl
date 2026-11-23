<?php

// Daily job: removes assistant conversations older than the retention period set by the admin.
\Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Assistant\Retention::prune();
