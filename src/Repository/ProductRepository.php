<?php

namespace App\Repository;

use App\Entity\Product;
use App\Model\ProductSearch;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Product>
 */
class ProductRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Product::class);
    }


    public function queryBuilderForProductSearch(ProductSearch $search): Query
    {
        $initialQuery = $this->createQueryBuilder('p')
            ->distinct()
            ->leftJoin('p.category', 'c')->addSelect('c')
            ->leftJoin('p.productVariants', 'v')->addSelect('v');

        //isActive checked
        if ($search->isActiveOnly()){
            $initialQuery->andWhere('p.active = :active')->setParameter('active', true);
        }

        //category
        if ($search->getCategory()){
            $initialQuery->andWhere('p.category = :cat')->setParameter('cat', $search->getCategory());
        }

        if ($search->getQ()){
            $q = mb_strtolower(trim($search->getQ()));
            $initialQuery->andWhere('LOWER(p.name) LIKE :q OR LOWER(p.description) LIKE :q')
                ->setParameter('q', '%'.$q.'%');
        }

        //Variant sku
        if ($search->getSku()){
            $sku = mb_strtolower(trim($search->getSku()));
            $initialQuery->andWhere('LOWER(v.stockKeepingUnit) LIKE :sku')
                ->setParameter('sku', '%'.$sku.'%');
        }

        //Price
        if (null !== $search->getMinPriceHT()) {
            $initialQuery
                ->andWhere('v.priceHT >= :min')
                ->setParameter('min', $search->getMinPriceHT() * 100);
        }
        if (null !== $search->getMaxPriceHT()) {
            $initialQuery
                ->andWhere('v.priceHT <= :max')
                ->setParameter('max', $search->getMaxPriceHT() * 100);
        }

        return $initialQuery->getQuery();

    }



    //    /**
    //     * @return Product[] Returns an array of Product objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('p')
    //            ->andWhere('p.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('p.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Product
    //    {
    //        return $this->createQueryBuilder('p')
    //            ->andWhere('p.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
