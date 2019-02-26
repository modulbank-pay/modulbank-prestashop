<?php
/**
 * Created by PhpStorm.
 * User: User
 * Date: 2019-02-06
 * Time: 14:51
 */


require_once(dirname(__FILE__) . '/../lib/fpayments.php');

use FPayments\FPaymentsRecieptItem;

class FPaymentsRecieptItemTest extends PHPUnit_Framework_TestCase
{

    public function testSplit_items_to_correct_price()
    {
        $item = new FPaymentsRecieptItem("test", 3, 3);
        $items = $item->split_items_to_correct_price(2);

        $this->assertEquals(
            0.66,
            $items[0]->as_dict()["price"]
        );
        $this->assertEquals(
            0.02,
            $items[1]->as_dict()["price"]
        );
    }

    public function testSplit_threeItemsOnRuble()
    {
        $item = new FPaymentsRecieptItem("test", 3, 3);
        $items = $item->split_items_to_correct_price(3);

        $this->assertEquals(
            1,
            $items[0]->as_dict()["price"]
        );

    }

    public function testSplit_oneItemNoChange()
    {
        $item = new FPaymentsRecieptItem("test", 3.34, 1);
        $items = $item->split_items_to_correct_price(3.33);

        $this->assertEquals(
            3.33,
            $items[0]->as_dict()["price"]
        );

    }

    public function testSplit_manyItemsNoChange()
    {
        $item = new FPaymentsRecieptItem("test", 1, 3);
        $items = $item->split_items_to_correct_price(3.00);

        $this->assertEquals(
            1,
            $items[0]->as_dict()["price"]
        );

    }
}
