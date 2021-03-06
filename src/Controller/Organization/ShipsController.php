<?php

namespace App\Controller\Organization;

use App\Domain\SpectrumIdentification;
use App\Repository\ShipRepository;
use App\Service\Organization\Fleet\FleetOrganizationGuard;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ShipsController extends AbstractController
{
    private FleetOrganizationGuard $fleetOrganizationGuard;
    private ShipRepository $shipRepository;

    public function __construct(FleetOrganizationGuard $fleetOrganizationGuard, ShipRepository $shipRepository)
    {
        $this->fleetOrganizationGuard = $fleetOrganizationGuard;
        $this->shipRepository = $shipRepository;
    }

    /**
     * @Route("/api/organization/{organizationSid}/ships", name="organization_ships", methods={"GET"})
     */
    public function __invoke(string $organizationSid): Response
    {
        if (null !== $response = $this->fleetOrganizationGuard->checkAccessibleOrganization($organizationSid)) {
            return $response;
        }

        $ships = $this->shipRepository->getFiltrableOrganizationShipNames(new SpectrumIdentification($organizationSid));

        $res = array_map(static function (array $ship) {
            return [
                'id' => $ship['galaxyId'],
                'label' => $ship['normalizedName'],
            ];
        }, $ships);

        return $this->json($res);
    }
}
