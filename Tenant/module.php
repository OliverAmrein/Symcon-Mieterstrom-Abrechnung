<?php

class Tenant extends IPSModule
{
    public function Create()
    {
        parent::Create();

        $this->RegisterVariableString("Adresse", "Adresse");

//        $this->RegisterPropertyString("CustomerName", "");


            // Zuordnung
        //$this->RegisterPropertyInteger("TenantID", 0);
        //$this->RegisterPropertyFloat("SharePercent", 100);


    }
    
    // Überschreibt die interne IPS_ApplyChanges($id) Funktion
    public function ApplyChanges(): void
    {
        // Diese Zeile nicht löschen
        parent::ApplyChanges();

        
        $this->SetValue("Adresse", $this->ReadPropertyString("Adresseingabe"));
    
    }

}
