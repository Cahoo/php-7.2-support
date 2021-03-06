<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\GraphQl\DownloadableProduct;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Downloadable\Api\Data\LinkInterface;
use Magento\Downloadable\Api\Data\SampleInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\TestFramework\ObjectManager;
use Magento\TestFramework\TestCase\GraphQlAbstract;

class DownloadableProductViewTest extends GraphQlAbstract
{
    /**
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     * @magentoApiDataFixture Magento/Downloadable/_files/downloadable_product_with_files_and_sample_url.php
     */
    public function testQueryAllFieldsDownloadableProductsWithDownloadableFileAndSample()
    {
        $productSku = 'downloadable-product';
        $query = <<<QUERY
{
  products(filter:{sku: {eq:"{$productSku}"}})
  {
       items{
           id
           attribute_set_id    
           created_at
           name
           sku
           type_id        
           updated_at
        price{
        regularPrice{
          amount{
            value
            currency
          }
          adjustments{
            code
            description
          }
        }
      }          
           category_ids                
           ... on DownloadableProduct {
            links_title
            links_purchased_separately
            
            downloadable_product_links{
              id
              sample_url
              sample_type
              
              is_shareable
              number_of_downloads
              sort_order
              title
              link_type
              
              price              
            }
            downloadable_product_samples{
              title
              sort_order
              sort_order
              sample_type
              sample_file
            }
           }
       }
   }
}
QUERY;

        /** @var \Magento\Config\Model\ResourceModel\Config $config */
        $config = ObjectManager::getInstance()->get(\Magento\Config\Model\ResourceModel\Config::class);
        $config->saveConfig(
            \Magento\Downloadable\Model\Link::XML_PATH_CONFIG_IS_SHAREABLE,
            0,
            ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
            0
        );
        $response = $this->graphQlQuery($query);
        /**
         * @var ProductRepositoryInterface $productRepository
         */
        $productRepository = ObjectManager::getInstance()->get(ProductRepositoryInterface::class);
        $downloadableProduct = $productRepository->get($productSku, false, null, true);
        $this->assertNull($downloadableProduct->getWeight());
        $IsLinksPurchasedSeparately = $downloadableProduct->getLinksPurchasedSeparately();
        $linksTitle = $downloadableProduct->getLinksTitle();
        $this->assertEquals(
            $IsLinksPurchasedSeparately,
            $response['products']['items'][0]['links_purchased_separately']
        );
        $this->assertEquals($linksTitle, $response['products']['items'][0]['links_title']);
        $this->assertDownloadableProductLinks($downloadableProduct, $response['products']['items'][0]);
        $this->assertDownloadableProductSamples($downloadableProduct, $response['products']['items'][0]);
    }

    /**
     * @magentoApiDataFixture Magento/Downloadable/_files/product_downloadable.php
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function testDownloadableProductQueryWithNoSample()
    {
        $productSku = 'downloadable-product';
        $query = <<<QUERY
{
  products(filter:{sku: {eq:"{$productSku}"}})
  {
       items{
           id
           attribute_set_id
           created_at
           name
           sku
           type_id
           updated_at
           ...on PhysicalProductInterface{
          weight
          }
        price{
        regularPrice{
          amount{
            value
            currency
          }
          adjustments{
            code
            description
          }
        }
      }
           category_ids
           ... on DownloadableProduct {
            links_title
            links_purchased_separately

            downloadable_product_links{
              id
              sample_url
              sample_type
              is_shareable
              number_of_downloads
              sort_order
              title
              link_type
              price
            }
            downloadable_product_samples{
              title
              sort_order
              sort_order
              sample_type
              sample_file
            }
           }
       }
   }
}
QUERY;
        $response = $this->graphQlQuery($query);
        /**
         * @var ProductRepositoryInterface $productRepository
         */
        $productRepository = ObjectManager::getInstance()->get(ProductRepositoryInterface::class);
        $downloadableProduct = $productRepository->get($productSku, false, null, true);
        /** @var \Magento\Config\Model\ResourceModel\Config $config */
        $config = ObjectManager::getInstance()->get(\Magento\Config\Model\ResourceModel\Config::class);
        $config->saveConfig(
            \Magento\Downloadable\Model\Link::XML_PATH_CONFIG_IS_SHAREABLE,
            0,
            ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
            1
        );
        $IsLinksPurchasedSeparately = $downloadableProduct->getLinksPurchasedSeparately();
        $linksTitle = $downloadableProduct->getLinksTitle();
        $this->assertEquals(
            $IsLinksPurchasedSeparately,
            $response['products']['items'][0]['links_purchased_separately']
        );
        $this->assertEquals($linksTitle, $response['products']['items'][0]['links_title']);
        $this->assertEmpty($response['products']['items'][0]['downloadable_product_samples']);
        $this->assertNotEmpty(
            $response['products']['items'][0]['downloadable_product_links'],
            "Precondition failed: 'downloadable_product_links' must not be empty"
        );
        /** @var LinkInterface $downloadableProductLinks */
        $downloadableProductLinks = $downloadableProduct->getExtensionAttributes()->getDownloadableProductLinks();
        $downloadableProductLink = $downloadableProductLinks[0];
        $this->assertResponseFields(
            $response['products']['items'][0]['downloadable_product_links'][0],
            [
                'id' => $downloadableProductLink->getId(),
                'is_shareable' => false,
                'number_of_downloads' => $downloadableProductLink->getNumberOfDownloads(),
                'sort_order' => $downloadableProductLink->getSortOrder(),
                'title' => $downloadableProductLink->getTitle(),
                'link_type' => strtoupper($downloadableProductLink->getLinkType()),
                'price' => $downloadableProductLink->getPrice()
            ]
        );
    }

    /**
     * @param ProductInterface $product
     * @param  array $actualResponse
     */
    private function assertDownloadableProductLinks($product, $actualResponse)
    {
        $this->assertNotEmpty(
            $actualResponse['downloadable_product_links'],
            "Precondition failed: 'downloadable_product_links' must not be empty"
        );
        /** @var LinkInterface $downloadableProductLinks */
        $downloadableProductLinks = $product->getExtensionAttributes()->getDownloadableProductLinks();
        $downloadableProductLink = $downloadableProductLinks[1];

        $this->assertResponseFields(
            $actualResponse['downloadable_product_links'][1],
            [
                'id' => $downloadableProductLink->getId(),
                'sample_url' => $downloadableProductLink->getSampleUrl(),
                'sample_type' => strtoupper($downloadableProductLink->getSampleType()),
                'is_shareable' => false,
                'number_of_downloads' => $downloadableProductLink->getNumberOfDownloads(),
                'sort_order' => $downloadableProductLink->getSortOrder(),
                'title' => $downloadableProductLink->getTitle(),
                'link_type' => strtoupper($downloadableProductLink->getLinkType()),
                'price' => $downloadableProductLink->getPrice()
            ]
        );
    }

    /**
     * @param ProductInterface $product
     * @param $actualResponse
     */
    private function assertDownloadableProductSamples($product, $actualResponse)
    {
        $this->assertNotEmpty(
            $actualResponse['downloadable_product_samples'],
            "Precondition failed: 'downloadable_product_samples' must not be empty"
        );
        /** @var SampleInterface $downloadableProductSamples */
        $downloadableProductSamples = $product->getExtensionAttributes()->getDownloadableProductSamples();
        $downloadableProductSample = $downloadableProductSamples[0];
        $this->assertResponseFields(
            $actualResponse['downloadable_product_samples'][0],
            [
                'title' => $downloadableProductSample->getTitle(),
                'sort_order' =>$downloadableProductSample->getSortOrder(),
                'sample_type' => strtoupper($downloadableProductSample->getSampleType()),
                'sample_file' => $downloadableProductSample->getSampleFile()
            ]
        );
    }

    /**
     * @param array $actualResponse
     * @param array $assertionMap ['response_field_name' => 'response_field_value', ...]
     *                         OR [['response_field' => $field, 'expected_value' => $value], ...]
     */
    private function assertResponseFields($actualResponse, $assertionMap)
    {
        foreach ($assertionMap as $key => $assertionData) {
            $expectedValue = isset($assertionData['expected_value'])
                ? $assertionData['expected_value']
                : $assertionData;
            $responseField = isset($assertionData['response_field']) ? $assertionData['response_field'] : $key;
            $this->assertNotNull(
                $expectedValue,
                "Value of '{$responseField}' field must not be NULL"
            );
            $this->assertEquals(
                $expectedValue,
                $actualResponse[$responseField],
                "Value of '{$responseField}' field in response does not match expected value: "
                . var_export($expectedValue, true)
            );
        }
    }
}
