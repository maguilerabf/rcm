<?php

namespace App\Repository;

use App\Entity\TelesaludSolicitud;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TelesaludSolicitud>
 */
class TelesaludSolicitudRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TelesaludSolicitud::class);
    }
}
