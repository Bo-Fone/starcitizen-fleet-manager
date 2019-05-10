<?php

namespace App\Controller;

use App\Domain\CitizenNumber;
use App\Domain\SpectrumIdentification;
use App\Entity\User;
use App\Exception\BadCitizenException;
use App\Exception\FleetUploadedTooCloseException;
use App\Exception\InvalidFleetDataException;
use App\Exception\NotFoundHandleSCException;
use App\Form\Dto\FleetUpload;
use App\Form\FleetUploadForm;
use App\Service\CitizenFleetGenerator;
use App\Service\FleetUploadHandler;
use App\Service\OrganisationFleetGenerator;
use Psr\Log\LoggerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Security;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @Route("/api", name="api_")
 */
class ApiController extends AbstractController
{
    private $logger;
    private $translator;
    private $security;
    private $fleetUploadHandler;
    private $citizenFleetGenerator;
    private $organisationFleetGenerator;

    public function __construct(
        LoggerInterface $logger,
        TranslatorInterface $translator,
        Security $security,
        FleetUploadHandler $fleetUploadHandler,
        CitizenFleetGenerator $citizenFleetGenerator,
        OrganisationFleetGenerator $organisationFleetGenerator
    ) {
        $this->logger = $logger;
        $this->translator = $translator;
        $this->security = $security;
        $this->fleetUploadHandler = $fleetUploadHandler;
        $this->citizenFleetGenerator = $citizenFleetGenerator;
        $this->organisationFleetGenerator = $organisationFleetGenerator;
    }

    /**
     * @Route("/me", name="me", methods={"GET"})
     * @IsGranted("IS_AUTHENTICATED_REMEMBERED")
     */
    public function me(): Response
    {
        return $this->json($this->security->getUser(), 200, [], ['groups' => 'me:read']);
    }

    /**
     * Preflight CORS request.
     *
     * @Route("/export", name="export_options", methods={"OPTIONS"})
     */
    public function exportOptions(): Response
    {
        return new JsonResponse(null, 204);
    }

    /**
     * @Route("/export", name="export", methods={"POST"}, condition="request.getContentType() == 'json'")
     * @IsGranted("IS_AUTHENTICATED_REMEMBERED")
     */
    public function export(Request $request): Response
    {
        $contents = $request->getContent();
        $fleetData = \json_decode($contents, true);

        if (JSON_ERROR_NONE !== $jsonError = json_last_error()) {
            $this->logger->error('Failed to decode json from fleet file', ['json_error' => $jsonError, 'fleet_file_contents' => $contents]);

            return $this->json([
                'error' => 'bad_json',
                'errorMessage' => sprintf('Your fleet file is not JSON well formatted. Please check it.'),
            ], 400);
        }

        return $this->handleFleetData($fleetData);
    }

    /**
     * Upload star citizen fleet for one user.
     *
     * @Route("/upload", name="upload", methods={"POST"})
     * @IsGranted("IS_AUTHENTICATED_REMEMBERED")
     */
    public function upload(Request $request, FormFactoryInterface $formFactory): Response
    {
        $fleetUpload = new FleetUpload();
        $form = $formFactory->createNamedBuilder('', FleetUploadForm::class, $fleetUpload)->getForm();
        $form->handleRequest($request);

        if (!$form->isSubmitted()) {
            return $this->json([
                'error' => 'not_submitted_form',
                'errorMessage' => 'No data has been submitted.',
            ], 400);
        }
        if (!$form->isValid()) {
            $formErrors = $form->getErrors(true);
            $errors = [];
            foreach ($formErrors as $formError) {
                $errors[] = $formError->getMessage();
            }
            $this->logger->warning('Upload fleet form error.', [
                'form_errors' => $errors,
            ]);

            return $this->json([
                'error' => 'invalid_form',
                'formErrors' => $errors,
            ], 400);
        }

        $fleetFileContents = file_get_contents($fleetUpload->fleetFile->getRealPath());
        $fleetData = \json_decode($fleetFileContents, true);
        if (JSON_ERROR_NONE !== $jsonError = json_last_error()) {
            $this->logger->error('Failed to decode json from fleet file', ['json_error' => $jsonError, 'fleet_file_contents' => $fleetFileContents]);

            return $this->json([
                'error' => 'bad_json',
                'errorMessage' => sprintf('Your fleet file is not JSON well formatted. Please check it.'),
            ], 400);
        }

        return $this->handleFleetData($fleetData);
    }

    private function handleFleetData(array $fleetData): Response
    {
        /** @var User $user */
        $user = $this->security->getUser();
        $citizen = $user->getCitizen();
        if ($citizen === null) {
            return $this->json([
                'error' => 'no_citizen_created',
                'errorMessage' => 'Your RSI account must be linked first. Go to the <a href="/#/profile">profile page</a>.',
            ], 400);
        }

        try {
            $this->fleetUploadHandler->handle($citizen, $fleetData);
        } catch (FleetUploadedTooCloseException $e) {
            return $this->json([
                'error' => 'uploaded_too_close',
                'errorMessage' => 'Your fleet has been uploaded recently. Please wait before re-uploading.',
            ], 400);
        } catch (NotFoundHandleSCException $e) {
            return $this->json([
                'error' => 'not_found_handle',
                'errorMessage' => sprintf('The SC handle %s does not exist.', $citizen->getActualHandle()),
                'context' => ['handle' => $citizen->getActualHandle()],
            ], 400);
        } catch (BadCitizenException $e) {
            return $this->json([
                'error' => 'bad_citizen',
                'errorMessage' => sprintf('Your SC handle has probably changed. Please update it in <a href="/#/profile">your Profile</a>.'),
            ], 400);
        } catch (InvalidFleetDataException $e) {
            return $this->json([
                'error' => 'invalid_fleet_data',
                'errorMessage' => sprintf('The fleet data in your file is invalid. Please check it.'),
            ], 400);
        } catch (\Exception $e) {
            $this->logger->error('cannot handle fleet file', ['exception' => $e]);

            return $this->json([
                'error' => 'cannot_handle_file',
                'errorMessage' => 'Cannot handle the fleet file. Try again !',
            ], 400);
        }

        return $this->json(null, 204);
    }

    /**
     * Combines the last version fleet of the given citizen.
     * Returns a downloadable json file.
     *
     * @Route("/create-citizen-fleet-file/{citizenNumber}", name="create_citizen_fleet_file", methods={"GET"})
     * @IsGranted("IS_AUTHENTICATED_REMEMBERED")
     */
    public function createCitizenFleetFile(string $citizenNumber): Response
    {
        try {
            $file = $this->citizenFleetGenerator->generateFleetFile(new CitizenNumber($citizenNumber));
        } catch (\Exception $e) {
            throw $this->createNotFoundException('The fleet file could not be generated.');
        }
        $filename = 'citizen_fleet.json';

        $response = new BinaryFileResponse($file);
        $response->headers->set('Content-Type', 'application/json');
        $response->deleteFileAfterSend();
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $filename,
            $filename
        );

        return $response;
    }

    /**
     * Combines all last version fleets of all citizen members of a specific organisation.
     * Returns a downloadable json file.
     *
     * @Route("/create-organisation-fleet-file/{organisation}", name="create_organisation_fleet_file", methods={"GET"})
     * @IsGranted("IS_AUTHENTICATED_REMEMBERED")
     */
    public function createOrganisationFleetFile(string $organisation): Response
    {
        $file = $this->organisationFleetGenerator->generateFleetFile(new SpectrumIdentification($organisation));
        $filename = 'organisation_fleet.json';

        $response = new BinaryFileResponse($file);
        $response->headers->set('Content-Type', 'application/json');
        $response->deleteFileAfterSend();
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $filename,
            $filename
        );

        return $response;
    }
}