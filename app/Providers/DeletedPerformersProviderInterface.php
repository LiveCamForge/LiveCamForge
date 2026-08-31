<?php

declare(strict_types=1);

namespace LiveCamForge\Providers;

interface DeletedPerformersProviderInterface
{
    /** @return list<string> */
    public function deletedUsernames(): array;
}
