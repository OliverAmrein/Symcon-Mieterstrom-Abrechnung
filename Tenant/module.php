<?php

class Tenant extends IPSModule
{
    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyString("CustomerName", "");
        $this->RegisterPropertyString("SenderAddress", "");
        $this->RegisterPropertyString("ReceiverAddress", "");
        $this->RegisterPropertyFloat("VAT", 7.7);
        $this->RegisterPropertyString("Currency", "CHF");
        $this->RegisterPropertyInteger("LogoID", 0);
    }
}
