<?php

namespace App\Controller;

use App\Domain\HandleSC;
use App\Entity\Citizen;
use App\Entity\Fleet;
use App\Entity\Organization;
use App\Entity\User;
use App\Exception\NotFoundHandleSCException;
use App\Form\Dto\LinkAccount;
use App\Form\LinkAccountForm;
use App\Repository\CitizenOrganizationRepository;
use App\Repository\CitizenRepository;
use App\Repository\OrganizationRepository;
use App\Repository\UserRepository;
use App\Service\CitizenFleetGenerator;
use App\Service\CitizenInfosProviderInterface;
use Doctrine\ORM\EntityManagerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Security;

/**
 * @Route("/api", name="api_")
 */
class ApiController extends AbstractController
{
    private $security;
    private $citizenFleetGenerator;
    private $organizationRepository;
    private $citizenRepository;
    private $userRepository;
    private $formFactory;

    public function __construct(
        Security $security,
        CitizenFleetGenerator $citizenFleetGenerator,
        OrganizationRepository $organizationRepository,
        CitizenRepository $citizenRepository,
        UserRepository $userRepository,
        FormFactoryInterface $formFactory
    ) {
        $this->security = $security;
        $this->citizenFleetGenerator = $citizenFleetGenerator;
        $this->organizationRepository = $organizationRepository;
        $this->citizenRepository = $citizenRepository;
        $this->userRepository = $userRepository;
        $this->formFactory = $formFactory;
    }

    /**
     * @Route("/citizen/{handle}", name="citizen", methods={"GET"}, options={"expose":true})
     *
     * Retrieves profile infos of a citizen.
     */
    public function index(string $handle): Response
    {
        /** @var Citizen|null $citizen */
        $citizen = $this->citizenRepository->findOneBy(['actualHandle' => $handle]);
        if ($citizen === null) {
            return $this->json([
                'error' => 'citizen_not_found',
                'errorMessage' => sprintf('The citizen %s does not exist.', $handle),
            ], 404);
        }

        /** @var User|null $user */
        $user = $this->userRepository->findOneBy(['citizen' => $citizen]);
        if ($user === null) {
            return $this->json([
                'error' => 'user_not_found',
                'errorMessage' => sprintf('The user of citizen %s does not exist.', $handle),
            ], 404);
        }

        if (!$this->isGranted('ACCESS_USER_FLEET', $user)) {
            return $this->json([
                'error' => 'no_rights',
                'errorMessage' => 'You have no rights to see this citizen.',
            ], 403);
        }

        return $this->json($citizen, 200, [], ['groups' => 'public_profile']);
    }

    /**
     * @Route("/search-handle", name="search_handle", methods={"GET"})
     */
    public function searchHandle(Request $request, CitizenInfosProviderInterface $citizenInfosProvider): Response
    {
        $handle = $request->query->get('handle');

        $linkAccount = new LinkAccount();
        $form = $this->formFactory->createNamedBuilder('', LinkAccountForm::class, $linkAccount)->getForm();
        $form->submit(['handleSC' => $handle]);

        if (!$form->isValid()) {
            $formErrors = $form->getErrors(true);
            $errors = [];
            foreach ($formErrors as $formError) {
                $errors[] = $formError->getMessage();
            }

            return $this->json([
                'error' => 'invalid_form',
                'formErrors' => $errors,
            ], 400);
        }

        try {
            $citizenInfos = $citizenInfosProvider->retrieveInfos(new HandleSC($linkAccount->handleSC));

            return $this->json($citizenInfos);
        } catch (NotFoundHandleSCException $e) {
        }

        return $this->json([
            'error' => 'not_found_handle',
            'errorMessage' => sprintf('The SC handle %s does not exist.', $handle),
        ], 404);
    }

    /**
     * @Route("/organization/{sid}", name="organization", methods={"GET"})
     */
    public function organization(string $sid): Response
    {
        $orga = $this->organizationRepository->findOneBy(['organizationSid' => $sid]);
        if ($orga === null) {
            return $this->json([
                'error' => 'orga_not_exist',
                'errorMessage' => sprintf('The organization %s does not exist.', $sid),
            ], 404);
        }

        return $this->json($orga);
    }

    /**
     * @Route("/manageable-organizations", name="manageable_organizations", methods={"GET"})
     * @IsGranted("IS_AUTHENTICATED_REMEMBERED")
     */
    public function manageableOrganizations(CitizenOrganizationRepository $citizenOrganizationRepository): Response
    {
        /** @var User $user */
        $user = $this->security->getUser();
        $citizen = $user->getCitizen();
        if ($citizen === null) {
            return $this->json([
                'error' => 'no_citizen_created',
                'errorMessage' => 'Your RSI account must be linked first. Go to the <a href="/profile">profile page</a>.',
            ], 400);
        }

        $sids = [];
        foreach ($citizen->getOrganizations() as $citizenOrga) {
            $citizenOrgas = $citizenOrganizationRepository->findGreaterThanRank($citizenOrga->getOrganizationSid(), $citizenOrga->getRank());
            if (count($citizenOrgas) === 0) {
                // granted to manage $citizenOrga settings
                $sids[] = $citizenOrga->getOrganizationSid();
            }
        }

        $manageableOrgas = $this->organizationRepository->findBy(['organizationSid' => $sids]);

        return $this->json($manageableOrgas);
    }

    /**
     * Combines the last version fleet of the given citizen.
     * Returns a downloadable json file.
     *
     * @Route("/create-citizen-fleet-file", name="create_citizen_fleet_file", methods={"GET"})
     * @IsGranted("IS_AUTHENTICATED_REMEMBERED")
     */
    public function createCitizenFleetFile(): Response
    {
        /** @var User $user */
        $user = $this->security->getUser();
        $citizen = $user->getCitizen();
        if ($citizen === null) {
            return new JsonResponse([
                'error' => 'no_citizen_created',
            ], 400);
        }

        try {
            $file = $this->citizenFleetGenerator->generateFleetFile($citizen->getNumber());
        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'file_not_generated',
            ], 400);
        }

        $fileResponse = $this->file($file, 'citizen_fleet.json');
        $fileResponse->headers->set('Content-Type', 'application/json');
        $fileResponse->deleteFileAfterSend();

        return $fileResponse;
    }

    /**
     * @Route("/numbers", name="numbers", methods={"GET"})
     */
    public function numbers(EntityManagerInterface $entityManager): Response
    {
        $countOrgas = count($entityManager->getRepository(Organization::class)->findAll());
        $countUsers = count($entityManager->getRepository(User::class)->findAll());
        $countShips = $entityManager->getRepository(Fleet::class)->countTotalShips();

        $response = $this->json([
            'organizations' => $countOrgas,
            'users' => $countUsers,
            'ships' => $countShips,
        ]);
        $response->setSharedMaxAge(300);

        return $response;
    }
}
