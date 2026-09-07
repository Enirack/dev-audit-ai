<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\Request\CreateRepositoryRequest;
use App\Entity\Enum\RepositoryProvider;
use App\Entity\Repository;
use App\Entity\User;
use App\Exception\DuplicateRepositoryException;
use App\Exception\GitHubApiException;
use App\Exception\GitHubRepositoryNotFoundException;
use App\Repository\RepositoryRepository;
use App\Service\GitHub\GitHubApiClient;
use App\Service\GitHub\GitHubUrlParser;
use Doctrine\ORM\EntityManagerInterface;

final class RepositoryImportService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly RepositoryRepository $repositoryRepository,
        private readonly GitHubUrlParser $urlParser,
        private readonly GitHubApiClient $apiClient,
    ) {
    }

    /**
     * @throws GitHubRepositoryNotFoundException if GitHub reports the repository as missing or private
     * @throws \App\Exception\InvalidGitHubUrlException if the URL fails validation (should already be caught by the request DTO's validator)
     * @throws DuplicateRepositoryException if this owner already imported this repository
     * @throws GitHubApiException if GitHub is unreachable or returns an unexpected status
     */
    public function importFromGitHubUrl(User $owner, CreateRepositoryRequest $request): Repository
    {
        $reference = $this->urlParser->parse($request->url);
        $canonicalUrl = $reference->canonicalUrl();

        if (null !== $this->repositoryRepository->findOneBy(['owner' => $owner, 'url' => $canonicalUrl])) {
            throw new DuplicateRepositoryException('This repository has already been added.');
        }

        $info = $this->apiClient->getRepositoryInfo($reference);

        $repository = new Repository($owner, $reference->fullName(), $canonicalUrl, RepositoryProvider::GitHub);
        $repository->setDefaultBranch($info['defaultBranch']);
        $repository->setDescription($request->description ?? $info['description']);

        $this->entityManager->persist($repository);
        $this->entityManager->flush();

        return $repository;
    }
}
