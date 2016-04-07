<?php

namespace Cart;

use N98\Magento\Command\AbstractMagentoCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Validator\Constraints;
use Symfony\Component\Validator\ConstraintValidatorFactory;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validator;

class AddItemCommand extends AbstractMagentoCommand
{
    protected function configure()
    {
        $this
            ->setName('cart:add-item')
            ->addArgument('sku', InputArgument::OPTIONAL, 'SKU')
            ->addArgument('qty', InputArgument::OPTIONAL, 'Quantity')
//            ->addArgument('website', InputArgument::OPTIONAL, 'Site')
            ->addArgument('email', InputArgument::OPTIONAL, 'Email')
            ->addArgument('store', InputArgument::OPTIONAL, 'Store')
            ->setDescription('Adds and Item to a user\s cart');
    }

    protected function askForCustomer(InputInterface $input, OutputInterface $output, $website)
    {
        $i = 0;
        do {
            $i++;
            $email = $this->getHelper('parameter')->askEmail($input, $output);
            $customer = $this->_getModel('customer/customer', 'Mage_Customer_Model_Customer')
                ->setWebsiteId($website->getId())
                ->loadByEmail($email);
            if (!$customer->getId()) {
                $output->writeln('No customer found with email ' . $email);
            }
        } while(!$customer->getId() && $i < 10);
        $output->writeln('Customer found!');
        return $customer;
    }

    protected function getProduct(InputInterface $input, OutputInterface $output, $store)
    {
        $sku = $input->getArgument('sku');


        if (!$sku) {
            /** @var DialogHelper $dialog */
            $dialog = $this->getHelperSet()->get('dialog');
            $question = '<question>SKU: </question>';
            $sku = $dialog->askAndValidate($output, $question, function($input) {
                return $input;
            });
        }

        $product_id = \Mage::getModel('catalog/product')->getIdBySku($sku);
        $product = $this->_getModel('catalog/product', 'Mage_Catalog_Model_Product')
            ->setStoreId($store->getId())
            ->load($product_id);

        if ($product->isObjectNew()) {
            throw new \Exception('No product with sku ' . $sku);
        }

        $output->writeln('Product found!');
        print_r($product);
        return $product;
    }

    protected function askQty(InputInterface $input, OutputInterface $output, $argumentName = 'qty')
    {
        $qty = $input->getArgument('qty');
        if ($qty) {
            return $qty;
        }


        /** @var DialogHelper $dialog */
        $dialog = $this->getHelperSet()->get('dialog');
        $question = '<question>Quantity: </question>';
        return $dialog->askAndValidate($output, $question, function($input) {
            return $input;
        });
    }

    /**
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     * @return int|void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->detectMagento($output);
        if ($this->initMagento()) {
            $store = $this->getHelper('parameter')->askStore($input, $output);
            $website = $store->getWebsite();
//            $website = $this->getHelper('parameter')->askWebsite($input, $output);
            $customer = $this->askForCustomer($input, $output, $website);
            $product = $this->getProduct($input, $output, $store);
            $qty = $this->askQty($input, $output);
            try {

                $quote = \Mage::getModel('sales/quote')
                    ->setStore($store)
                    ->loadByCustomer($customer->getId());

//                $cartModel = \Mage::getModel('checkout/cart');
//                $cartModel->setData('quote', $quote);
//                $cartModel->init();
//                $cartModel->addProduct($product, array('qty'=>$qty));
//                $cartModel->save();

                $quote->assignCustomer($customer);
                $quote->addProduct($product, $qty);
                $quote->setIsActive(1);
                $quote->collectTotals()->save();

            }
            catch(Exception $e)
            {
                $output->writeln($e->getMessage());
            }

        }
    }
}
