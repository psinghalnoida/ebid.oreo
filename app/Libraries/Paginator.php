<?php

namespace App\Libraries;

// Phase 3A: a small, shared page/per_page helper — every "My *" list
// page needs the same three lines, so this exists to avoid repeating
// them five times rather than as a general framework.
class Paginator
{
    public static function fromRequest(\CodeIgniter\HTTP\IncomingRequest $request, int $defaultPerPage = 20, int $maxPerPage = 100): array
    {
        $page = max(1, (int) ($request->getGet('page') ?: 1));
        $perPage = (int) ($request->getGet('per_page') ?: $defaultPerPage);
        $perPage = max(1, min($maxPerPage, $perPage));

        return ['page' => $page, 'perPage' => $perPage, 'offset' => ($page - 1) * $perPage];
    }

    public static function totalPages(int $totalCount, int $perPage): int
    {
        return $perPage > 0 ? (int) ceil($totalCount / $perPage) : 1;
    }
}
