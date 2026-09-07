<?php

declare(strict_types=1);

namespace App\Command;

use App\Dto\Request\CreateRepositoryRequest;
use App\Entity\RepositoryScan;
use App\Entity\User;
use App\Exception\GitHubApiException;
use App\Exception\GitHubRepositoryNotFoundException;
use App\Repository\RepositoryRepository;
use App\Repository\RepositoryScanRepository;
use App\Repository\UserRepository;
use App\Service\RepositoryImportService;
use App\Service\Scanning\RepositoryScanOrchestrator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Seeds a demo account and runs real audits against a handful of small,
 * genuinely public GitHub repositories through the exact same pipeline a
 * real user's "Start audit" click uses — no fabricated findings, scores, or
 * statistics anywhere. Safe to re-run: it reuses the demo user and skips
 * repositories that already have a completed scan unless --refresh is given.
 */
#[AsCommand(name: 'app:demo:seed', description: 'Seed a demo account with real audits of a few small public GitHub repositories.')]
final class SeedDemoDataCommand extends Command
{
    private const DEMO_EMAIL = 'demo@devaudit.local';
    private const DEMO_PASSWORD = 'DemoPassword123!';

    /** @var string[] */
    private const DEMO_REPOSITORY_URLS = [
        'https://github.com/sindresorhus/is-plain-obj',
        'https://github.com/jonschlinkert/is-number',
        'https://github.com/octocat/Hello-World',
    ];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
        private readonly RepositoryRepository $repositoryRepository,
        private readonly RepositoryScanRepository $scanRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly RepositoryImportService $importService,
        private readonly RepositoryScanOrchestrator $orchestrator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('refresh', null, InputOption::VALUE_NONE, 'Re-run audits even for repositories that already have a completed scan.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $refresh = (bool) $input->getOption('refresh');

        $user = $this->userRepository->findByEmail(self::DEMO_EMAIL);
        if (null === $user) {
            $user = new User(self::DEMO_EMAIL, '');
            $user->setPassword($this->passwordHasher->hashPassword($user, self::DEMO_PASSWORD));
            $this->entityManager->persist($user);
            $this->entityManager->flush();
            $io->success(sprintf('Created demo user: %s / %s', self::DEMO_EMAIL, self::DEMO_PASSWORD));
        } else {
            $io->note(sprintf('Demo user already exists: %s', self::DEMO_EMAIL));
        }

        foreach (self::DEMO_REPOSITORY_URLS as $url) {
            $existing = $this->findExistingRepository($user, $url);

            if (null === $existing) {
                $request = new CreateRepositoryRequest();
                $request->url = $url;

                try {
                    $repository = $this->importService->importFromGitHubUrl($user, $request);
                } catch (GitHubRepositoryNotFoundException|GitHubApiException $e) {
                    $io->warning("Skipping {$url}: {$e->getMessage()}");
                    continue;
                }

                $io->writeln("Imported {$repository->getName()}");
            } else {
                $repository = $existing;
            }

            $scans = $this->scanRepository->findByRepository($repository);
            $hasCompletedScan = array_any($scans, static fn (RepositoryScan $scan) => $scan->getStatus()->value === 'completed');

            if ($hasCompletedScan && !$refresh) {
                $io->writeln("  {$repository->getName()} already audited — skipping (use --refresh to re-run).");
                continue;
            }

            $io->writeln("  Running a real audit for {$repository->getName()}...");
            $scan = new RepositoryScan($repository, $user);
            $this->entityManager->persist($scan);
            $this->entityManager->flush();

            $this->orchestrator->run($scan);
            $io->writeln("  status: {$scan->getStatus()->value}"
                .(null !== $scan->getErrorMessage() ? " ({$scan->getErrorMessage()})" : ''));
        }

        $io->success(sprintf('Demo data ready. Log in at the frontend with %s / %s', self::DEMO_EMAIL, self::DEMO_PASSWORD));

        return Command::SUCCESS;
    }

    private function findExistingRepository(User $user, string $url): ?\App\Entity\Repository
    {
        foreach ($this->repositoryRepository->findByOwner($user) as $repository) {
            if (rtrim($repository->getUrl(), '/') === rtrim($url, '/')) {
                return $repository;
            }
        }

        return null;
    }
}
