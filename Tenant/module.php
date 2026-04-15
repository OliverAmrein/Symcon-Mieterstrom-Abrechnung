<?php

class Tenant extends IPSModule
{

    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyString("Adresse","(Adresse erfassen)");
        $this->RegisterPropertyString("Mietername","(hier Mieter Kurzname erfassen)");
        $this->RegisterPropertyString("Objektname","(hier Objektname erfassen)");

       
        $this->RegisterPropertyString("Zählerliste", "[]");

        $this->RegisterPropertyInteger("Rabatt", 0);
    }
    
    // Überschreibt die interne IPS_ApplyChanges($id) Funktion
    public function ApplyChanges(): void
    {
        // Diese Zeile nicht löschen
        parent::ApplyChanges();

        $Mieter = IPS_GetProperty ($this->InstanceID, "Mietername");
        IPS_SetName($this->InstanceID, "Mieter ".$Mieter);
    
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
