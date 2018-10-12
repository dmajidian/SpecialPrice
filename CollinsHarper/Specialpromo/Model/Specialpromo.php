<?php

class Collinsharper_Specialpromo_Model_Specialpromo extends Mage_Core_Model_Abstract
{
    const INDEXER_TO_RUN = 'catalog_category_product';//'catalogsearch_fulltext';
    const PROMO_CATEGORY = 'Specials/Promos'; //'Gear Up for Under $1000';
    const PROMO_DISCOUNT = 25;
    const EXCLUDE        = 'tire';
    protected function _construct()
    {
        $this->_init('collinsharper_specialpromo/specialpromo');
    }

    public function run()
    {
        try
        {
            $categoryId      = $this->getSpecialsCategoryId();
            $specialproducts = $this->getSpecialProducts();
            $count           = count($specialproducts);

            $this->clearSpecials($categoryId);
            $this->sendMessage('Adding ' . $count . ' product(s) to ' . self::PROMO_CATEGORY . ' (ID: ' . $categoryId . ') that have a discount of ' . self::PROMO_DISCOUNT . '% or greater.');
            $i=0;
            foreach ($specialproducts as $product)
            {
                if(!Mage::getSingleton('catalog/category_api')
                    ->assignProduct($categoryId, $product))
                {
                    $this->sendMessage('Failed to add product id ' . $product . ' to ' . self::PROMO_CATEGORY . '.');
                }
                $i++;
                $this->status($i, $count, 30);
            }

            $this->runIndex(self::INDEXER_TO_RUN);
            $this->clearCache();
        }
        catch(\Exception $e)
        {
            echo $e->getMessage();
        }
    }

    protected function getExcludedCategories()
    {
        $categories = Mage::getModel('catalog/category')->getCollection()
            ->addAttributeToSelect('*')
            ->addAttributeToFilter('is_active', true)
            ->addFieldToFilter('name', array('like' => '%'. self::EXCLUDE . '%'))
            ->load();
        $excluded = array();

        if(count($categories) > 0)
        {
            $i=0;
            foreach($categories as $item)
            {
                $excluded[$i] = $item->getId();
                $i++;
            }
        }

        return $excluded;
    }

    protected function getSpecialProducts()
    {
        $products = Mage::getModel('catalog/product')->getCollection()
            ->addAttributeToSelect('*')
            ->addFieldToFilter('type_id', array('eq'=> 'configurable'))
            ->addFieldToFilter('status', array('eq'=> 1))
            ->addFieldToFilter('visibility', array('gteq'=> 2))
            ->addFieldToFilter('price', array('gt'=> 0))
            ->addFieldToFilter('msrp', array('gt'=> 0))
            //->setPageSize(100)
            ->setCurPage(1);
        $products->getSelect()->order(new Zend_Db_Expr('RAND()'));

        $specialproducts = array();
        if(count($products) > 0)
        {
            $i = 0;
            foreach ($products as $product) {
                if ($product->isInStock()) {
                    $matches = array_intersect($product->getCategoryIds(), $this->getExcludedCategories());

                    if (count($matches) === 0) {

                        $specialPriceToDate = $product->getSpecialToDate();
                        $specialPrice       = $product->getSpecialPrice();

                        if ($specialPriceToDate > date("Y-m-d h:i:s")) {
                            $useSpecialPrice = true;
                        } elseif (empty($specialPriceToDate)) {
                            $useSpecialPrice = true;
                        } else {
                            $useSpecialPrice = false;
                        }

                        if ($useSpecialPrice && !empty($specialPrice)) {
                            $sprice = (float) $product->getSpecialPrice();
                        } elseif (!$useSpecialPrice) {
                            $sprice = (float) $product->getPrice();
                        } else {
                            $sprice = (float) $product->getPrice();
                        }

                        $id = $product->getId();
                        $msrp = (float) $product->getMsrp();
                        $percent = round((($msrp - $sprice) * 100) / $msrp);

                        if ($percent >= self::PROMO_DISCOUNT && $percent < 100) {
                            $specialproducts[$i] = $id;
                            $i++;
                        }
                    }
                }
            }
        }

        return $specialproducts;
    }

    protected function getSpecialsCategoryId()
    {
        $categorySpecial = Mage::getModel('catalog/category')->getCollection()
            ->addAttributeToSelect('*')
            ->addAttributeToFilter('is_active', true)
            ->addAttributeToFilter('name', self::PROMO_CATEGORY)
            ->getFirstItem();

        $catId = $categorySpecial->getId();

        if(!empty($catId))
        {
            return $categorySpecial->getId();
        }
        else {
            throw new Exception('Could not find ' . self::PROMO_CATEGORY . ' category. Please add it.');
        }
    }

    protected function clearSpecials($categoryId)
    {
        $this->sendMessage('Clearing category ' . self::PROMO_CATEGORY . '.');
        $category = Mage::getModel('catalog/category')->load($categoryId);
        $category->setPostedProducts(array());
        if($category->save())
        {
            $this->sendMessage('Category Cleared.');
        } else {
            throw new Exception('Could not clear ' . self::PROMO_CATEGORY . '.');
        }
    }

    protected function runIndex($code)
    {
        $this->sendMessage('Starting Indexer: ' . $code);
        $process = Mage::getModel('index/indexer')->getProcessByCode($code);
        if($process->reindexAll())
        {
            $this->sendMessage('Indexer finished.');
        } else {
            throw new Exception('Could not run indexer.');
        }
    }

    protected function clearCache()
    {
        if (Mage::app()->getCacheInstance()->flush()) {
            $this->sendMessage('Cache flushed.');
        } else {
            throw new Exception('Could not flush cache.');
        }
    }

    protected function status($done, $total, $size=30)
    {
        static $start_time;

        if($done > $total) return;

        if(empty($start_time)) $start_time=time();
        $now = time();

        $perc=(double)($done/$total);

        $bar=floor($perc*$size);

        $status_bar="\r[";
        $status_bar.=str_repeat("=", $bar);
        if($bar<$size)
        {
            $status_bar.=">";
            $status_bar.=str_repeat(" ", $size-$bar);
        } else {
            $status_bar.="=";
        }

        $disp=number_format($perc*100, 0);

        $status_bar.="] $disp%  $done/$total";

        $rate = ($now-$start_time)/$done;
        $left = $total - $done;
        $eta = round($rate * $left, 2);

        $elapsed = $now - $start_time;

        $status_bar.= " remaining: ".number_format($eta)." sec.  elapsed: ".number_format($elapsed)." sec.";

        echo "$status_bar  ";

        flush();

        if($done == $total)
        {
            echo "\n";
        }
    }

    protected function sendMessage($message)
    {
        echo $message;
        echo "\n";
        flush();
    }
}
