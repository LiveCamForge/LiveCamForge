<?php

declare(strict_types=1);

namespace LiveCamForge\Postbacks;

interface PostbackHandlerInterface
{
    /** @return array{status:int,body:array<string,mixed>} */
    public function handle(array $payload): array;
}
