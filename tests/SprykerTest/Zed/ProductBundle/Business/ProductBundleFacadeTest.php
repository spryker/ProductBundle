<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace SprykerTest\Zed\ProductBundle\Business;

use Codeception\Test\Unit;
use Generated\Shared\DataBuilder\QuoteBuilder;
use Generated\Shared\Transfer\CartChangeTransfer;
use Generated\Shared\Transfer\CheckoutResponseTransfer;
use Generated\Shared\Transfer\CurrencyTransfer;
use Generated\Shared\Transfer\ItemTransfer;
use Generated\Shared\Transfer\MoneyValueTransfer;
use Generated\Shared\Transfer\PriceProductTransfer;
use Generated\Shared\Transfer\PriceTypeTransfer;
use Generated\Shared\Transfer\ProductAbstractTransfer;
use Generated\Shared\Transfer\ProductBundleTransfer;
use Generated\Shared\Transfer\ProductConcreteTransfer;
use Generated\Shared\Transfer\ProductForBundleTransfer;
use Generated\Shared\Transfer\QuoteTransfer;
use Generated\Shared\Transfer\StockProductTransfer;
use Generated\Shared\Transfer\StoreTransfer;
use Orm\Zed\ProductBundle\Persistence\SpyProductBundleQuery;
use Spryker\Zed\Kernel\Locator;
use Spryker\Zed\ProductBundle\Business\ProductBundle\Cart\ProductBundleCartItemGroupKeyExpander;
use Spryker\Zed\ProductBundle\Business\ProductBundleFacade;
use Spryker\Zed\ProductBundle\Dependency\Facade\ProductBundleToProductBridge;
use Spryker\Zed\ProductBundle\Dependency\QueryContainer\ProductBundleToAvailabilityQueryContainerBridge;

/**
 * Auto-generated group annotations
 * @group SprykerTest
 * @group Zed
 * @group ProductBundle
 * @group Business
 * @group Facade
 * @group ProductBundleFacadeTest
 * Add your own group annotations below this line
 */
class ProductBundleFacadeTest extends Unit
{
    public const SKU_BUNDLED_1 = 'sku-1-test-tester';
    public const SKU_BUNDLED_2 = 'sku-2-test-tester';
    public const BUNDLE_SKU_3 = 'sku-3-test-tester';

    public const BUNDLED_PRODUCT_PRICE_1 = 50;
    public const BUNDLED_PRODUCT_PRICE_2 = 100;
    public const ID_STORE = 1;

    protected const INT_QUANTITY = 1;
    protected const FLOAT_QUANTITY = 1.5;

    /**
     * @return void
     */
    public function testExpandBundleItemsShouldCreateBundleItemsAndCalculateSplitPrice()
    {
        $this->markTestSkipped();
        $bundlePrice = self::BUNDLED_PRODUCT_PRICE_2;

        $productConcreteTransfer = $this->createProductBundle($bundlePrice);

        $cartChangeTransfer = new CartChangeTransfer();
        $currencyTransfer = new CurrencyTransfer();
        $currencyTransfer->setCode('EUR');
        $quoteTransfer = $this->createBaseQuoteTransfer();
        $quoteTransfer->setCurrency($currencyTransfer);
        $cartChangeTransfer->setQuote($quoteTransfer);

        $itemTransfer = new ItemTransfer();
        $itemTransfer->setQuantity(self::ID_STORE);
        $itemTransfer->setUnitGrossPrice($productConcreteTransfer->getPrices()[0]->getPrice());
        $itemTransfer->setId($productConcreteTransfer->getIdProductConcrete());
        $itemTransfer->setSku($productConcreteTransfer->getSku());

        $cartChangeTransfer->addItem($itemTransfer);

        $productBundleFacade = $this->createProductBundleFacade();
        $cartChangeTransfer = $productBundleFacade->expandBundleItems($cartChangeTransfer);

        $quoteTransfer = $cartChangeTransfer->getQuote();

        $this->assertCount(3, $cartChangeTransfer->getItems());
        $this->assertCount(self::ID_STORE, $quoteTransfer->getBundleItems());

        $bundledItemTransfer = $quoteTransfer->getBundleItems()[0];
        $bundleItemIdentifier = $bundledItemTransfer->getBundleItemIdentifier();

        $this->assertSame($bundlePrice, $bundledItemTransfer->getUnitGrossPrice());

        $itemTransfer = $cartChangeTransfer->getItems()[0];
        $this->assertSame(20, $itemTransfer->getUnitGrossPrice());
        $this->assertSame($bundleItemIdentifier, $itemTransfer->getRelatedBundleItemIdentifier());

        $itemTransfer = $cartChangeTransfer->getItems()[self::ID_STORE];
        $itemTransfer->getUnitGrossPrice();
        $this->assertSame(40, $itemTransfer->getUnitGrossPrice());
        $this->assertSame($bundleItemIdentifier, $itemTransfer->getRelatedBundleItemIdentifier());

        $itemTransfer = $cartChangeTransfer->getItems()[2];
        $itemTransfer->getUnitGrossPrice();
        $this->assertSame(40, $itemTransfer->getUnitGrossPrice());
        $this->assertSame($bundleItemIdentifier, $itemTransfer->getRelatedBundleItemIdentifier());
    }

    /**
     * @return void
     */
    public function testExpandCartItemGroupKeyShouldAppendBundleToKey()
    {
        $productBundleFacade = $this->createProductBundleFacade();

        $cartChangeTransfer = new CartChangeTransfer();

        $groupKeyBefore = 'test1';
        $itemTransfer = new ItemTransfer();
        $itemTransfer->setGroupKey($groupKeyBefore);
        $itemTransfer->setRelatedBundleItemIdentifier('related1');

        $cartChangeTransfer->addItem($itemTransfer);

        $cartChangeTransfer = $productBundleFacade->expandBundleCartItemGroupKey($cartChangeTransfer);

        $itemTransfer = $cartChangeTransfer->getItems()[0];

        $this->assertEquals(
            $groupKeyBefore . ProductBundleCartItemGroupKeyExpander::GROUP_KEY_DELIMITER . $itemTransfer->getRelatedBundleItemIdentifier() . '1',
            $itemTransfer->getGroupKey()
        );
    }

    /**
     * @return void
     */
    public function testPostSaveCartUpdateWhenBundleRemoveShouldReturnQuoteWithouBundles()
    {
        $quoteTransfer = $this->createBaseQuoteTransfer();

        $itemTransfer = new ItemTransfer();
        $itemTransfer->setBundleItemIdentifier('bundleid');

        $quoteTransfer->addBundleItem($itemTransfer);

        $productBundleFacade = $this->createProductBundleFacade();
        $quoteTransfer = $productBundleFacade->postSaveCartUpdateBundles($quoteTransfer);

        $this->assertCount(0, $quoteTransfer->getBundleItems());
    }

    /**
     * @dataProvider preCheckCartAvailabilityWhenBundleAvailableProvider
     *
     * @param \Generated\Shared\Transfer\CartChangeTransfer $cartChangeTransfer
     *
     * @return void
     */
    public function testPreCheckCartAvailabilityWhenBundleAvailable(CartChangeTransfer $cartChangeTransfer)
    {
        $this->createProductBundle(static::BUNDLED_PRODUCT_PRICE_2, true);

        $productBundleFacade = $this->createProductBundleFacade();

        $cartChangeTransfer = $productBundleFacade->preCheckCartAvailability($cartChangeTransfer);

        $this->assertTrue($cartChangeTransfer->getIsSuccess());
    }

    /**
     * @return array
     */
    public function preCheckCartAvailabilityWhenBundleAvailableProvider(): array
    {
        return [
            'int stock' => $this->preCheckCartAvailabilityWhenBundleAvailableData(static::INT_QUANTITY),
            'float stock' => $this->preCheckCartAvailabilityWhenBundleAvailableData(static::FLOAT_QUANTITY),
        ];
    }

    /**
     * @param float $quantity
     *
     * @return array
     */
    public function preCheckCartAvailabilityWhenBundleAvailableData(float $quantity): array
    {
        $cartChangeTransfer = new CartChangeTransfer();

        $itemTransfer = new ItemTransfer();
        $itemTransfer->setSku(static::BUNDLE_SKU_3);

        $quoteTransfer = $this->createBaseQuoteTransfer();
        $quoteTransfer->addItem($itemTransfer);

        $cartChangeTransfer->setQuote($quoteTransfer);

        $itemTransfer = new ItemTransfer();
        $itemTransfer->setSku(static::BUNDLE_SKU_3);
        $itemTransfer->setQuantity($quantity);

        $cartChangeTransfer->addItem($itemTransfer);

        return [$cartChangeTransfer];
    }

    /**
     * @dataProvider preCheckCartAvailabilityWhenBundleUnavailableProvider
     *
     * @param \Generated\Shared\Transfer\CartChangeTransfer $cartChangeTransfer
     *
     * @return void
     */
    public function testPreCheckCartAvailabilityWhenBundleUnavailable(CartChangeTransfer $cartChangeTransfer)
    {
        $this->createProductBundle(self::BUNDLED_PRODUCT_PRICE_2);

        $productBundleFacade = $this->createProductBundleFacade();

        $cartChangeTransfer = $productBundleFacade->preCheckCartAvailability($cartChangeTransfer);

        $this->assertFalse($cartChangeTransfer->getIsSuccess());
    }

    /**
     * @return array
     */
    public function preCheckCartAvailabilityWhenBundleUnavailableProvider(): array
    {
        return [
            'int stock' => $this->preCheckCartAvailabilityWhenBundleUnavailableData(5, 25),
            'float stock' => $this->preCheckCartAvailabilityWhenBundleUnavailableData(5.5, 25.5),
        ];
    }

    /**
     * @param float $firstQuantity
     * @param float $secondQuantity
     *
     * @return array
     */
    protected function preCheckCartAvailabilityWhenBundleUnavailableData(float $firstQuantity, float $secondQuantity): array
    {
        $cartChangeTransfer = new CartChangeTransfer();

        $itemTransfer = new ItemTransfer();
        $itemTransfer->setQuantity($firstQuantity);
        $itemTransfer->setSku(static::BUNDLE_SKU_3);

        $quoteTransfer = $this->createBaseQuoteTransfer();
        $quoteTransfer->addItem($itemTransfer);

        $cartChangeTransfer->setQuote($quoteTransfer);

        $itemTransfer = new ItemTransfer();
        $itemTransfer->setSku(self::SKU_BUNDLED_1);
        $itemTransfer->setQuantity($secondQuantity);

        $cartChangeTransfer->addItem($itemTransfer);

        return [$cartChangeTransfer];
    }

    /**
     * @return void
     */
    public function testPreCheckCheckoutAvailabilityWhenBundleUnavailable()
    {
        $productConcreteTransfer = $this->createProductBundle(self::BUNDLED_PRODUCT_PRICE_2);

        $productBundleFacade = $this->createProductBundleFacade();

        $quoteTransfer = $this->createBaseQuoteTransfer();

        $itemTransfer = new ItemTransfer();
        $itemTransfer->setQuantity(50);
        $itemTransfer->setSku(self::SKU_BUNDLED_1);

        $quoteTransfer->addItem($itemTransfer);

        $itemTransfer = new ItemTransfer();
        $itemTransfer->setQuantity(self::ID_STORE);
        $itemTransfer->setSku($productConcreteTransfer->getSku());
        $quoteTransfer->addBundleItem($itemTransfer);

        $checkoutResponseTransfer = new CheckoutResponseTransfer();

        $productBundleFacade->preCheckCheckoutAvailability($quoteTransfer, $checkoutResponseTransfer);

        $this->assertFalse($checkoutResponseTransfer->getIsSuccess());
    }

    /**
     * @return void
     */
    public function testPreCheckCheckoutAvailabilityWhenBundleAvailable()
    {
        $productConcreteTransfer = $this->createProductBundle(self::BUNDLED_PRODUCT_PRICE_2, true);

        $productBundleFacade = $this->createProductBundleFacade();

        $quoteTransfer = $this->createBaseQuoteTransfer();

        $itemTransfer = new ItemTransfer();
        $itemTransfer->setQuantity(self::ID_STORE);
        $itemTransfer->setSku(self::SKU_BUNDLED_1);

        $quoteTransfer->addItem($itemTransfer);

        $itemTransfer = new ItemTransfer();
        $itemTransfer->setQuantity(self::ID_STORE);
        $itemTransfer->setSku($productConcreteTransfer->getSku());
        $quoteTransfer->addBundleItem($itemTransfer);

        $checkoutResponseTransfer = new CheckoutResponseTransfer();

        $productBundleFacade->preCheckCheckoutAvailability($quoteTransfer, $checkoutResponseTransfer);

        $this->assertNull($checkoutResponseTransfer->getIsSuccess());
    }

    /**
     * @return void
     */
    public function testUpdateAffectedBundleAvailabilityWhenOneOfBundledItemsUnavailable()
    {
        $this->createProductBundle(self::BUNDLED_PRODUCT_PRICE_2);

        $productBundleFacade = $this->createProductBundleFacade();

        $availabilityQueryContainer = $this->createAvailabilityQueryContainer();
        $bundledProductAvailability = $availabilityQueryContainer->querySpyAvailabilityBySku(self::SKU_BUNDLED_2, self::ID_STORE)->findOne();

        $bundledProductAvailability->setQuantity(0)
            ->save();

        $productBundleFacade->updateAffectedBundlesAvailability(self::SKU_BUNDLED_2);

        $bundledProductAvailability = $availabilityQueryContainer->querySpyAvailabilityBySku(self::BUNDLE_SKU_3, self::ID_STORE)->findOne();

        $this->assertSame(0.0, $bundledProductAvailability->getQuantity());
    }

    /**
     * @dataProvider saveBundledProductsShouldAddProvidedConcreteToBundleProvider
     *
     * @param \Generated\Shared\Transfer\ProductForBundleTransfer $bundledProductTransfer
     *
     * @return void
     */
    public function testSaveBundledProductsShouldAddProvidedConcreteToBundle(
        ProductForBundleTransfer $bundledProductTransfer
    ) {
        $productConcreteBundleTransfer = $this->createProduct(self::BUNDLED_PRODUCT_PRICE_1, self::BUNDLE_SKU_3);
        $productConcreateToAssignTransfer = $this->createProduct(self::BUNDLED_PRODUCT_PRICE_1, self::SKU_BUNDLED_1);

        $productBundleFacade = $this->createProductBundleFacade();

        $productBundleTransfer = new ProductBundleTransfer();

        $bundledProductTransfer->setIdProductConcrete($productConcreateToAssignTransfer->getIdProductConcrete());
        $bundledProductTransfer->setIdProductBundle($productConcreteBundleTransfer->getIdProductConcrete());

        $productBundleTransfer->addBundledProduct($bundledProductTransfer);

        $productConcreteBundleTransfer->setProductBundle($productBundleTransfer);

        $productConcreteBundleTransfer = $productBundleFacade->saveBundledProducts($productConcreteBundleTransfer);

        $bundledProducts = SpyProductBundleQuery::create()->findByFkProduct($productConcreteBundleTransfer->getIdProductConcrete());

        $this->assertCount(self::ID_STORE, $bundledProducts);

        $bundledProductEntity = $bundledProducts[0];

        $this->assertSame($bundledProductTransfer->getQuantity(), $bundledProductEntity->getQuantity());
        $this->assertSame($productConcreateToAssignTransfer->getIdProductConcrete(), $bundledProductEntity->getFkBundledProduct());
        $this->assertSame($productConcreteBundleTransfer->getIdProductConcrete(), $bundledProductEntity->getFkProduct());
    }

    /**
     * @return array
     */
    public function saveBundledProductsShouldAddProvidedConcreteToBundleProvider(): array
    {
        return [
            'int stock' => $this->saveBundledProductsShouldAddProvidedConcreteToBundleData(2.0),
            'float stock' => $this->saveBundledProductsShouldAddProvidedConcreteToBundleData(2.5),
        ];
    }

    /**
     * @param float $quantity
     *
     * @return array
     */
    public function saveBundledProductsShouldAddProvidedConcreteToBundleData(float $quantity): array
    {
        $bundledProductTransfer = new ProductForBundleTransfer();
        $bundledProductTransfer->setQuantity($quantity);

        return [$bundledProductTransfer];
    }

    /**
     * @return void
     */
    public function testSaveBundledProductsWhenRemoveListProvidedShouldRemoveBundledProducts()
    {
        $this->markTestIncomplete('Something with transactions');
        $productConcreteBundleTransfer = $this->createProductBundle(self::BUNDLED_PRODUCT_PRICE_2);

        $productBundleFacade = $this->createProductBundleFacade();

        $productBundleTransfer = $productConcreteBundleTransfer->getProductBundle();
        $bundledProducts = $productBundleTransfer->getBundledProducts();
        $productBundleTransfer->setBundlesToRemove([$bundledProducts[0]->getIdProductConcrete()]);
        $productConcreteBundleTransfer->setProductBundle($productBundleTransfer);

        $productConcreteBundleTransfer = $productBundleFacade->saveBundledProducts($productConcreteBundleTransfer);

        $bundledProducts = SpyProductBundleQuery::create()->findByFkProduct($productConcreteBundleTransfer->getIdProductConcrete());

        $this->assertCount(self::ID_STORE, $bundledProducts);
    }

    /**
     * @return void
     */
    public function testFindBundledProductsByIdProductConcreteShouldReturnPersistedBundledProducts()
    {
        $this->markTestIncomplete('Something with transactions');
        $productConcreteBundleTransfer = $this->createProductBundle(self::BUNDLED_PRODUCT_PRICE_2);

        $productBundleFacade = $this->createProductBundleFacade();
        $bundledProducts = $productBundleFacade->findBundledProductsByIdProductConcrete(
            $productConcreteBundleTransfer->getIdProductConcrete()
        );

        $this->assertCount(2, $bundledProducts);
    }

    /**
     * @return void
     */
    public function testAssignBundledProductsToProductConcreteShouldAssignPersistedBundledProducts()
    {
        $this->markTestIncomplete('Something with transactions');
        $productConcreteBundleTransfer = $this->createProductBundle(self::BUNDLED_PRODUCT_PRICE_2);

        $productConcreteTransfer = new ProductConcreteTransfer();
        $productConcreteTransfer->setIdProductConcrete($productConcreteBundleTransfer->getIdProductConcrete());

        $productBundleFacade = $this->createProductBundleFacade();
        $productConcreteTransfer = $productBundleFacade->assignBundledProductsToProductConcrete($productConcreteTransfer);

        $this->assertNotNull($productConcreteTransfer->getProductBundle());
        $this->assertCount(2, $productConcreteTransfer->getProductBundle()->getBundledProducts());
    }

    /**
     * @return void
     */
    public function testFilterBundleItemsOnCartReloadShouldRemoveBundleItems()
    {
        $productBundleFacade = $this->createProductBundleFacade();

        $quoteTransfer = (new QuoteBuilder())
            ->withBundleItem([
                ItemTransfer::BUNDLE_ITEM_IDENTIFIER => self::ID_STORE,
            ])->withItem([
                ItemTransfer::RELATED_BUNDLE_ITEM_IDENTIFIER => self::ID_STORE,
            ])->withAnotherItem([
                ItemTransfer::SKU => '123',
            ])
            ->build();

        $updatedQuoteTransfer = $productBundleFacade->filterBundleItemsOnCartReload($quoteTransfer);

        $this->assertCount(0, $updatedQuoteTransfer->getBundleItems());
        $this->assertCount(2, $updatedQuoteTransfer->getItems());
    }

    /**
     * @return \Spryker\Zed\ProductBundle\Business\ProductBundleFacade
     */
    protected function createProductBundleFacade()
    {
        return new ProductBundleFacade();
    }

    /**
     * @return \Spryker\Zed\ProductBundle\Dependency\Facade\ProductBundleToProductBridge
     */
    protected function createProductFacade()
    {
        return new ProductBundleToProductBridge($this->getLocator()->product()->facade());
    }

    /**
     * @return \Spryker\Zed\ProductBundle\Dependency\QueryContainer\ProductBundleToAvailabilityQueryContainerBridge
     */
    protected function createAvailabilityQueryContainer()
    {
        return new ProductBundleToAvailabilityQueryContainerBridge($this->getLocator()->availability()->queryContainer());
    }

    /**
     * @return \Generated\Zed\Ide\AutoCompletion|\Spryker\Shared\Kernel\LocatorLocatorInterface
     */
    protected function getLocator()
    {
        return Locator::getInstance();
    }

    /**
     * @param int $price
     * @param string $sku
     * @param bool $isAlwaysAvailable
     *
     * @return \Generated\Shared\Transfer\ProductConcreteTransfer
     */
    protected function createProduct($price, $sku, $isAlwaysAvailable = false)
    {
        $productFacade = $this->createProductFacade();
        $productAbstractTransfer = new ProductAbstractTransfer();
        $productAbstractTransfer->setSku("random_" . sha1(random_bytes(50)));

        $priceProductTransfer = new PriceProductTransfer();
        $priceProductTransfer->setSkuProductAbstract($productAbstractTransfer->getSku());

        $priceTypeTransfer = new PriceTypeTransfer();
        $priceTypeTransfer->setName($this->getLocator()->priceProduct()->facade()->getDefaultPriceTypeName());
        $priceProductTransfer->setPriceType($priceTypeTransfer);

        $currencyTransfer = $this->getLocator()->currency()->facade()->fromIsoCode('EUR');
        $storeTransfer = $this->getLocator()->store()->facade()->getCurrentStore();

        $moneyValueTransfer = (new MoneyValueTransfer())
            ->setCurrency($currencyTransfer)
            ->setFkCurrency($currencyTransfer->getIdCurrency())
            ->setFkStore($storeTransfer->getIdStore())
            ->setNetAmount($price)
            ->setGrossAmount($price);

        $priceProductTransfer->setMoneyValue($moneyValueTransfer);

        $productAbstractTransfer->addPrice($priceProductTransfer);
        $productAbstractTransfer->setAttributes([]);

        $concreteProductCollection = [];

        $stockProductTransfer = new StockProductTransfer();
        $stockProductTransfer->setSku($sku);
        $stockProductTransfer->setQuantity(10);
        $stockProductTransfer->setStockType('Warehouse1');

        $productConcreteTransfer = new ProductConcreteTransfer();
        $productConcreteTransfer->addStock($stockProductTransfer);
        $productConcreteTransfer->setSku($sku);
        if ($isAlwaysAvailable) {
            $productConcreteTransfer->setIsActive(true);
        } else {
            $productConcreteTransfer->setIsActive(false);
        }
        $productConcreteTransfer->setPrices($productAbstractTransfer->getPrices());
        $productConcreteTransfer->setLocalizedAttributes($productAbstractTransfer->getLocalizedAttributes());

        $concreteProductCollection[] = $productConcreteTransfer;

        $productFacade->addProduct($productAbstractTransfer, $concreteProductCollection);

        return $productConcreteTransfer;
    }

    /**
     * @param int $bundlePrice
     * @param bool $isAlwaysAvailable
     *
     * @return \Generated\Shared\Transfer\ProductConcreteTransfer
     */
    protected function createProductBundle($bundlePrice, $isAlwaysAvailable = false)
    {
        $productConcreteTransferToAssign1 = $this->createProduct(self::BUNDLED_PRODUCT_PRICE_1, self::SKU_BUNDLED_1, $isAlwaysAvailable);
        $productConcreteTransferToAssign2 = $this->createProduct(self::BUNDLED_PRODUCT_PRICE_2, self::SKU_BUNDLED_2, $isAlwaysAvailable);

        $productBundleTransfer = new ProductBundleTransfer();
        if ($isAlwaysAvailable) {
            $productBundleTransfer->setIsNeverOutOfStock(true);
        }

        $bundledProductTransfer = new ProductForBundleTransfer();
        $bundledProductTransfer->setQuantity(self::ID_STORE);
        $bundledProductTransfer->setIdProductConcrete($productConcreteTransferToAssign1->getIdProductConcrete());
        $productBundleTransfer->addBundledProduct($bundledProductTransfer);

        $bundledProductTransfer = new ProductForBundleTransfer();
        $bundledProductTransfer->setQuantity(2);
        $bundledProductTransfer->setIdProductConcrete($productConcreteTransferToAssign2->getIdProductConcrete());
        $productBundleTransfer->addBundledProduct($bundledProductTransfer);

        $productConcreteTransfer = $this->createProduct($bundlePrice, self::BUNDLE_SKU_3, $isAlwaysAvailable);
        $productConcreteTransfer->setProductBundle($productBundleTransfer);

        $productBundleFacade = $this->createProductBundleFacade();
        $productBundleFacade->saveBundledProducts($productConcreteTransfer);

        return $productConcreteTransfer;
    }

    /**
     * @return \Generated\Shared\Transfer\QuoteTransfer
     */
    protected function createBaseQuoteTransfer()
    {
        $storeTransfer = (new StoreTransfer())->setName('DE');

        return (new QuoteTransfer())
            ->setStore($storeTransfer);
    }
}
