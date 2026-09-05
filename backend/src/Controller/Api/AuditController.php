<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Dto\Request\FindingFilterCriteria;
use App\Dto\Response\AuditFindingResponse;
use App\Dto\Response\AuditResponse;
use App\Dto\Response\AuditScoreResponse;
use App\Dto\Response\PaginatedFindingsResponse;
use App\Entity\Audit;
use App\Entity\User;
use App\Repository\AuditFindingRepository;
use App\Repository\AuditRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/audits/{id}')]
final class AuditController
{
    public function __construct(
        private readonly AuditRepository $auditRepository,
        private readonly AuditFindingRepository $auditFindingRepository,
    ) {
    }

    #[Route('', name: 'api_audits_show', methods: ['GET'])]
    public function show(string $id, #[CurrentUser] User $user): JsonResponse
    {
        $audit = $this->findOwnedAudit($id, $user);
        if (null === $audit) {
            return new JsonResponse(['error' => 'Audit not found.'], 404);
        }

        return new JsonResponse(AuditResponse::fromEntity($audit));
    }

    #[Route('/scores', name: 'api_audits_scores', methods: ['GET'])]
    public function scores(string $id, #[CurrentUser] User $user): JsonResponse
    {
        $audit = $this->findOwnedAudit($id, $user);
        if (null === $audit) {
            return new JsonResponse(['error' => 'Audit not found.'], 404);
        }

        return new JsonResponse(array_map(AuditScoreResponse::fromEntity(...), $audit->getScores()->toArray()));
    }

    #[Route('/findings', name: 'api_audits_findings', methods: ['GET'])]
    public function findings(string $id, Request $request, #[CurrentUser] User $user): JsonResponse
    {
        $audit = $this->findOwnedAudit($id, $user);
        if (null === $audit) {
            return new JsonResponse(['error' => 'Audit not found.'], 404);
        }

        $criteria = FindingFilterCriteria::fromRequest($request);
        $result = $this->auditFindingRepository->findByAuditFiltered($audit, $criteria);

        $totalPages = 0 === $result['total'] ? 0 : (int) ceil($result['total'] / $criteria->perPage);

        return new JsonResponse(new PaginatedFindingsResponse(
            data: array_map(AuditFindingResponse::fromEntity(...), $result['items']),
            page: $criteria->page,
            perPage: $criteria->perPage,
            total: $result['total'],
            totalPages: $totalPages,
        ));
    }

    #[Route('/statistics', name: 'api_audits_statistics', methods: ['GET'])]
    public function statistics(string $id, #[CurrentUser] User $user): JsonResponse
    {
        $audit = $this->findOwnedAudit($id, $user);
        if (null === $audit) {
            return new JsonResponse(['error' => 'Audit not found.'], 404);
        }

        $inventory = $audit->getRepositoryScan()->getInventory();

        $findingsByCategory = [];
        $findingsBySeverity = [];
        foreach ($audit->getFindings() as $finding) {
            $category = $finding->getCategory()->value;
            $severity = $finding->getSeverity()->value;
            $findingsByCategory[$category] = ($findingsByCategory[$category] ?? 0) + 1;
            $findingsBySeverity[$severity] = ($findingsBySeverity[$severity] ?? 0) + 1;
        }

        return new JsonResponse([
            'languages' => $inventory?->getLanguageStats() ?? [],
            'fileCount' => $inventory?->getTotalFiles() ?? 0,
            'totalSizeBytes' => $inventory?->getTotalSizeBytes() ?? 0,
            'testDirectories' => $inventory?->getMetadata()['testDirectories'] ?? [],
            'metadataFlags' => $inventory?->getMetadata() ?? [],
            'findingsByCategory' => $findingsByCategory,
            'findingsBySeverity' => $findingsBySeverity,
        ]);
    }

    #[Route('/report', name: 'api_audits_report', methods: ['GET'])]
    public function report(string $id, #[CurrentUser] User $user): JsonResponse
    {
        $audit = $this->findOwnedAudit($id, $user);
        if (null === $audit) {
            return new JsonResponse(['error' => 'Audit not found.'], 404);
        }

        $inventory = $audit->getRepositoryScan()->getInventory();
        $repository = $audit->getRepositoryScan()->getRepository();

        return new JsonResponse([
            'auditId' => (string) $audit->getId(),
            'repositoryId' => (string) $repository->getId(),
            'repositoryName' => $repository->getName(),
            'generatedAt' => $audit->getGeneratedAt()->format(DATE_ATOM),
            'overallScore' => $audit->getOverallScore(),
            'categoryScores' => array_map(AuditScoreResponse::fromEntity(...), $audit->getScores()->toArray()),
            'repositoryStatistics' => [
                'languages' => $inventory?->getLanguageStats() ?? [],
                'totalFiles' => $inventory?->getTotalFiles() ?? 0,
                'testDirectories' => $inventory?->getMetadata()['testDirectories'] ?? [],
                'metadataFlags' => $inventory?->getMetadata() ?? [],
            ],
            'findings' => array_map(AuditFindingResponse::fromEntity(...), $audit->getFindings()->toArray()),
        ]);
    }

    private function findOwnedAudit(string $id, User $user): ?Audit
    {
        $audit = $this->auditRepository->find($id);
        if (null === $audit) {
            return null;
        }

        $owner = $audit->getRepositoryScan()->getRepository()->getOwner();
        if ($owner->getId()->toRfc4122() !== $user->getId()->toRfc4122()) {
            return null;
        }

        return $audit;
    }
}
