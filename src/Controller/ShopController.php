<?php

namespace App\Controller;

use App\Form\ProductSearchType;
use App\Model\ProductSearch;
use App\Repository\ProductRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Doctrine\ORM\Tools\Pagination\Paginator;

#[Route('/produits', name: 'shop_products_')]
final class ShopController extends AbstractController
{
    public function __construct(
        private ProductRepository $productRepository,
    ){}


    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $search = new ProductSearch();
        $form = $this->createForm(ProductSearchType::class, $search);
        $form->handleRequest($request);

        $page = max(1, $request->query->getInt('page', 1));
        $limit = 24;

        $query = $this->productRepository
            ->queryBuilderForProductSearch($search)
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        $paginator = new Paginator($query, true);
        $totalItems = count($paginator);
        $totalPages = max(1, (int) ceil($totalItems / $limit));

        return $this->render('shop/index.html.twig', [
            'products' => iterator_to_array($paginator),
            'searchForm' => $form->createView(),
            'search' => $search,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total_items' => $totalItems,
                'total_pages' => $totalPages,
            ],
        ]);
    }

    #[Route('/{slug}', name: 'show', methods: ['GET'])]
    public function show(string $slug): Response
    {
        $product = $this->productRepository
            ->createQueryBuilder('p')
            ->leftJoin('p.productVariants', 'v')->addSelect('v')
            ->andWhere('p.slug = :slug')
            ->setParameter('slug', $slug)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$product) {
            throw $this->createNotFoundException('Produit introuvable.');
        }

        return $this->render('shop/show.html.twig', [
            'product' => $product,
        ]);
    }

    #[Route('/{slug}/availability', name: 'availability', methods: ['GET'])]
    public function availability(string $slug, Request $request): JsonResponse
    {
        $product = $this->productRepository->findOneBy(['slug' => $slug]);

        if (!$product) {
            return $this->json(['error' => 'Produit introuvable.'], Response::HTTP_NOT_FOUND);
        }

        $city = trim($request->query->getString('city', ''));

        // searching for productVariant data
        $items = [];
        foreach ($product->getProductVariants() as $variant) {
            $items[] = [
                'sku' => $variant->getStockKeepingUnit(),
                'name' => $variant->getName(),
                'active' => $variant->isActive(),
                'price_ht' => $variant->getPriceHT(),
                'vat_rate' => $variant->getVatRate(),
                'available_qty' => null,
            ];
        }

        return $this->json([
            'city' => $city !== '' ? $city : null,
            'items' => $items,
            'note' => 'Aucun modèle de stock/ville n’est encore implémenté.',
        ]);
    }
}
