<?php
/**
 * If Special Price is empty, then convert MSRP to Price, and Price to Special Price (Switcheroo)
 * Author: David Majidian
 * Date: 08/26/2018
 */
class Collinsharper_Switchprice_Model_Observer
    extends Mage_Core_Model_Abstract
{
    public function switchprice($observer)
    {
        $event        = $observer->getEvent();
        $product      = $event->getProduct();

        $specialPriceToDate = $product->getSpecialToDate();
        $specialPrice = $product->getSpecialPrice();
        $price        = $product->getPrice();
        $msrp = $product->getMsrp();

        if ($specialPriceToDate > date("Y-m-d h:i:s"))
        {
            $useSpecialPrice = true;
        } elseif (empty($specialPriceToDate))
        {
            $useSpecialPrice = true;
        } else {
            $useSpecialPrice = false;
        }

        if(
            !empty($msrp) &&
            empty($specialPrice) &&
            $price <  $msrp
        )
        {
            $product->setPrice($msrp);
            $product->setSpecialPrice($price);
        } else if(
            !empty($msrp) &&
            !empty($specialPrice) &&
            !empty($price) &&
            $price < $msrp &&
            $useSpecialPrice
        )
        {
            $product->setPrice($msrp);
        } else if (
            !empty($msrp) &&
            !empty($specialPrice) &&
            !empty($price) &&
            $price < $msrp &&
            !$useSpecialPrice)
        {
            $product->setPrice($msrp);
            $product->setSpecialPrice($price);
        }

        return $this;
    }

    public function switchpriceview($observer)
    {
        $products = $observer->getCollection();

        foreach( $products as $product )
        {
            $specialPriceToDate = $product->getSpecialToDate();
            $specialPrice = $product->getSpecialPrice();
            $price        = $product->getPrice();
            $msrp = $product->getMsrp();

            if ($specialPriceToDate > date("Y-m-d h:i:s"))
            {
                $useSpecialPrice = true;
            } elseif (empty($specialPriceToDate))
            {
                $useSpecialPrice = true;
            } else {
                $useSpecialPrice = false;
            }

            if(
                !empty($msrp) &&
                empty($specialPrice) &&
                $price <  $msrp
            )
            {
                $product->setPrice($msrp);
                $product->setSpecialPrice($price);
            } else if(
                !empty($msrp) &&
                !empty($specialPrice) &&
                !empty($price) &&
                $price < $msrp &&
                $useSpecialPrice
            )
            {
                $product->setPrice($msrp);
            } else if (
                !empty($msrp) &&
                !empty($specialPrice) &&
                !empty($price) &&
                $price < $msrp &&
                !$useSpecialPrice)
            {
                $product->setPrice($msrp);
                $product->setSpecialPrice($price);
            }
        }

        return $this;
    }
}
