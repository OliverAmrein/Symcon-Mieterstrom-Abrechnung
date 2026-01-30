<?php

class Tenant extends IPSModule
{
    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyString("Adresse");

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

        $this->RegisterVariableString("Adresse", "Adresse", "");
        //$this->SetValue("Adresse", $this->ReadPropertyString("Adresseingabe"));
    
    }

    /**
		* This function will be available automatically after the module is imported with the module control.
		* Using the custom prefix this function will be callable from PHP and JSON-RPC through:
		*
		* EL_RequestInfo($id);
		*
		*/
		public function RequestInfo()
		{
		
			
			$ad = $this->ReadPropertyString("Adresse");
			
			SetValue($this->GetIDForIdent("Adresse"), $ad);
			
		}
	
	}

}
