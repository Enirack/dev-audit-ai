<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\Request\CreateRepositoryRequest;
use App\Entity\Enum\RepositoryProvider;
use App\Entity\Repository;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

final class RepositoryImportService
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function importFromGitHubUrl(User $owner, CreateRepositoryRequest $request): Repository
    {
        $name = $this->extractNameFromUrl($request->url);

        $repository = new Repository($owner, $name, rtrim($request->url, '/'), RepositoryProvider::GitHub);
        $repository->setDescription($request->description);

        $this->entityManager->persist($repository);
        $this->entityManager->flush();

        return $repository;
    }

    private function extractNameFromUrl(string $url): string
    {
        $path = trim((string) parse_url(rtrim($url, '/'), PHP_URL_PATH), '/');
        $segments = explode('/', $path);

        return implode('/', array_slice($segments, 0, 2));
    }
}
