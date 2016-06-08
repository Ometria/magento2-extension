<?php

namespace Ometria\Core\Model\Observer; 

class Newsletter 
{
    protected $helperPing;
    protected $frontendAreaChecker;
    protected $helperCookiechannel;
    
    public function __construct(
        \Ometria\Core\Helper\Ping $helperPing,
        \Ometria\Core\Helper\Is\Frontend $frontendAreaChecker,            
        \Ometria\Core\Helper\Cookiechannel $helperCookiechannel       
    )
    {    
        $this->helperPing = $helperPing;
        $this->frontendAreaChecker = $frontendAreaChecker;
        $this->helperCookiechannel = $helperCookiechannel;        
    }
    
    public function handleSubscriberUpdate(\Magento\Framework\Event\Observer $observer){
        $ometria_ping_helper = $this->helperPing;

        $subscriber = $observer->getEvent()->getSubscriber();

        $data = $subscriber->getData();

        $original_data = $subscriber->getOrigData();
        if (!$original_data) {
            $status_change = true;
        } elseif (isset($original_data['subscriber_status'])) {
            $status_change = $data['subscriber_status'] != $original_data['subscriber_status'];
        }

        // Only if status has changed
        if ($status_change) {
            $event = null;
            if ($data['subscriber_status']==1) $event = 'newsletter_subscribed';
            if ($data['subscriber_status']==3) $event = 'newsletter_unsubscribed';
            if ($event) $ometria_ping_helper->sendPing($event, $subscriber->getEmail(), array('store_id'=>$subscriber->getStoreId()), $subscriber->getStoreId());

            // Update timestamp column
            $subscriber->setData('change_status_at', date("Y-m-d H:i:s", time()));
        }

        // If via front end, also identify via cookie channel (but do not replace if customer login has done it)
        $is_frontend = true;
        if(!$this->frontendAreaChecker->check())
        {
            $is_frontend = false;
        }
        if ($is_frontend){
            $ometria_cookiechannel_helper = $this->helperCookiechannel;
            $data = array('e'=>$subscriber->getEmail());
            $command = array('identify', 'newsletter', http_build_query($data));
            $ometria_cookiechannel_helper->addCommand($command, false);
        }
    }

    public function handleSubscriberDeletion(\Magento\Framework\Event\Observer $observer){
        $ometria_ping_helper = $this->helperPing;

        $subscriber = $observer->getEvent()->getSubscriber();
        $ometria_ping_helper->sendPing('newsletter_unsubscribed', $subscriber->getEmail(), array('store_id'=>$subscriber->getStoreId()), $subscriber->getStoreId());
    }
}