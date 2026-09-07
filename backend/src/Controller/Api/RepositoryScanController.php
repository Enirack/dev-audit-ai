<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Dto\Response\RepositoryScanResponse;
use App\Entity\Repository as RepositoryEntity;
use App\Entity\RepositoryScan;
use App\Entity\User;
use App\Repository\RepositoryRepository;
use App\Repository\RepositoryScanRepository;
use App\Service\Scanning\RepositoryScanOrchestrator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/repositories/{repositoryId}/scans')]
final class RepositoryScanController
{
    public function __construct(
        private readonly RepositoryRepository $repositoryRepository,
        private readonly RepositoryScanRepository $scanRepository,
        private readonly RepositoryScanOrchestrator $orchestrator,
        private readonly EntityManagerInterface $entityManager,
        #[Autowire(service: 'limiter.repository_scan')]
        private readonly RateLimiterFactory $scanLimiter,
    ) {
    }

    #[Route('', name: 'api_repository_scans_list', methods: ['GET'])]
    public function list(string $repositoryId, #[CurrentUser] User $user): JsonResponse
    {
        $repository = $this->findOwnedRepository($repositoryId, $user);
        if (null === $repository) {
            return new JsonResponse(['error' => 'Repository not found.'], 404);
        }

        $scans = array_map(
            RepositoryScanResponse::fromEntity(...),
            $this->scanRepository->findByRepository($repository),
        );

        return new JsonResponse($scans);
    }

    #[Route('', name: 'api_repository_scans_create', methods: ['POST'])]
    public function create(string $repositoryId, #[CurrentUser] User $user): JsonResponse
    {
        $repository = $this->findOwnedRepository($repositoryId, $user);
        if (null === $repository) {
            return new JsonResponse(['error' => 'Repository not found.'], 404);
        }

        if (!$this->scanLimiter->create($user->getId()->toRfc4122())->consume()->isAccepted()) {
            return new JsonResponse(['error' => 'Too many audits started recently. Please wait before starting another.'], 429);
        }

        $scan = new RepositoryScan($repository, $user);
        $this->entityManager->persist($scan);
        $this->entityManager->flush();

        // Synchronous for V1: no message queue is set up yet, so the request
        // blocks until ingestion (and, from Phase 3 on, scanning) finishes.
        $this->orchestrator->run($scan);

        return new JsonResponse(RepositoryScanResponse::fromEntity($scan), 201);
    }

    #[Route('/{scanId}', name: 'api_repository_scans_show', methods: ['GET'])]
    public function show(string $repositoryId, string $scanId, #[CurrentUser] User $user): JsonResponse
    {
        $repository = $this->findOwnedRepository($repositoryId, $user);
        if (null === $repository) {
            return new JsonResponse(['error' => 'Repository not found.'], 404);
        }

        $scan = $this->scanRepository->find($scanId);
        if (null === $scan || $scan->getRepository()->getId()->toRfc4122() !== $repository->getId()->toRfc4122()) {
            return new JsonResponse(['error' => 'Scan not found.'], 404);
        }

        return new JsonResponse(RepositoryScanResponse::fromEntity($scan));
    }

    private function findOwnedRepository(string $repositoryId, User $user): ?RepositoryEntity
    {
        $repository = $this->repositoryRepository->find($repositoryId);
        if (null === $repository || $repository->getOwner()->getId()->toRfc4122() !== $user->getId()->toRfc4122()) {
            return null;
        }

        return $repository;
    }
}
