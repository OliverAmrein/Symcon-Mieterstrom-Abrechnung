<?php

class Tenant extends IPSModule
{

    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyString("Adresse","(hier Adresse refassen)");

       
        $this->RegisterPropertyString("Zählerliste", "[]");

        $this->RegisterPropertyInteger("Rabatt", 0);
    }
    
    // Überschreibt die interne IPS_ApplyChanges($id) Funktion
    public function ApplyChanges(): void
    {
        // Diese Zeile nicht löschen
        parent::ApplyChanges();

    
    }

    /**
		* This function will be available automatically after the module is imported with the module control.
		* Using the custom prefix this function will be callable from PHP and JSON-RPC through:
		*
		* EL_RequestInfo($id);
		*
		*/ 
    // public function RequestInfo()
    // {
    
        
    //     $ad = $this->ReadPropertyString("Adresse");
        
    //     SetValue($this->GetIDForIdent("Adresse"), $ad);
        
    // }
	
	

}
