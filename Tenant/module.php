<?php

class Tenant extends IPSModule
{
    public function Create()
    {
        parent::Create();

        $this->RegisterVariableString("Adresse", "");

//        $this->RegisterPropertyString("CustomerName", "");


            // Zuordnung
        //$this->RegisterPropertyInteger("TenantID", 0);
        //$this->RegisterPropertyFloat("SharePercent", 100);


    }
}
