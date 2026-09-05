<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Dto\Request\RegisterUserRequest;
use App\Dto\Response\UserResponse;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api')]
final class AuthController
{
    public function __construct(
        private readonly SerializerInterface $serializer,
        private readonly ValidatorInterface $validator,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/register', name: 'api_register', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        /** @var RegisterUserRequest $dto */
        $dto = $this->serializer->deserialize($request->getContent(), RegisterUserRequest::class, 'json');

        $violations = $this->validator->validate($dto);
        if (count($violations) > 0) {
            return new JsonResponse(['errors' => (string) $violations], 422);
        }

        if (null !== $this->userRepository->findByEmail($dto->email)) {
            return new JsonResponse(['error' => 'A user with this email already exists.'], 409);
        }

        $user = new User($dto->email, '');
        $user->setPassword($this->passwordHasher->hashPassword($user, $dto->password));

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return new JsonResponse(UserResponse::fromEntity($user), 201);
    }

    #[Route('/login', name: 'api_login', methods: ['POST'])]
    public function login(): void
    {
        // Intercepted by the api_login firewall's json_login authenticator;
        // this action is never actually executed.
        throw new \LogicException('This action should never be reached.');
    }

    #[Route('/me', name: 'api_me', methods: ['GET'])]
    public function me(#[CurrentUser] User $user): JsonResponse
    {
        return new JsonResponse(UserResponse::fromEntity($user));
    }
}
