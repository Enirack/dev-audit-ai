<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Dto\Request\CreateRepositoryRequest;
use App\Dto\Response\RepositoryResponse;
use App\Entity\User;
use App\Exception\DuplicateRepositoryException;
use App\Exception\GitHubApiException;
use App\Exception\GitHubRepositoryNotFoundException;
use App\Repository\RepositoryRepository;
use App\Service\RepositoryImportService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/repositories')]
final class RepositoryController
{
    public function __construct(
        private readonly SerializerInterface $serializer,
        private readonly ValidatorInterface $validator,
        private readonly RepositoryImportService $repositoryImportService,
        private readonly RepositoryRepository $repositoryRepository,
    ) {
    }

    #[Route('', name: 'api_repositories_list', methods: ['GET'])]
    public function list(#[CurrentUser] User $user): JsonResponse
    {
        $repositories = array_map(
            RepositoryResponse::fromEntity(...),
            $this->repositoryRepository->findByOwner($user),
        );

        return new JsonResponse($repositories);
    }

    #[Route('', name: 'api_repositories_create', methods: ['POST'])]
    public function create(Request $request, #[CurrentUser] User $user): JsonResponse
    {
        /** @var CreateRepositoryRequest $dto */
        $dto = $this->serializer->deserialize($request->getContent(), CreateRepositoryRequest::class, 'json');

        $violations = $this->validator->validate($dto);
        if (count($violations) > 0) {
            return new JsonResponse(['errors' => (string) $violations], 422);
        }

        try {
            $repository = $this->repositoryImportService->importFromGitHubUrl($user, $dto);
        } catch (DuplicateRepositoryException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 409);
        } catch (GitHubRepositoryNotFoundException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 422);
        } catch (GitHubApiException) {
            return new JsonResponse(['error' => 'Unable to reach GitHub. Please try again later.'], 503);
        }

        return new JsonResponse(RepositoryResponse::fromEntity($repository), 201);
    }

    #[Route('/{id}', name: 'api_repositories_show', methods: ['GET'])]
    public function show(string $id, #[CurrentUser] User $user): JsonResponse
    {
        $repository = $this->repositoryRepository->find($id);

        if (null === $repository || $repository->getOwner()->getId()->toRfc4122() !== $user->getId()->toRfc4122()) {
            return new JsonResponse(['error' => 'Repository not found.'], 404);
        }

        return new JsonResponse(RepositoryResponse::fromEntity($repository));
    }
}
