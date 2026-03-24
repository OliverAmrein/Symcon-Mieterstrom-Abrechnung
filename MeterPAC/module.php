<?php

class MeterPAC extends IPSModule
{
    public function Create()
    {
        parent::Create();


        $this->RegisterPropertyString("IPAdresse","(hier IP-Adresse erfassen)");
        $this->RegisterPropertyString("Anlagenkennzeichen","");
        $this->RegisterPropertyString("Ortskennzeichen","");

        // Messwerte
        //$this->RegisterVariableFloat("EnergyWh", "Energie gesamt (Wh)");
        //$this->RegisterVariableFloat("EnergykWh", "Energie gesamt (kWh)");
        //$this->RegisterVariableFloat("PeriodConsumptionkWh", "Periodenverbrauch (kWh)");

        // Polling
        //$this->RegisterTimer("Update", 300000, 'PAC_Update($_IPS["TARGET"]);');
    }

    public function Aktualisieren(): void
    {
        echo "Start Aktualisieren";
        $IP = GetValue($this->ReadPropertyString("IPAdresse"));
        $response = file_get_contents('http://'.$IP.'/data.json?type=DEVICE_INFO');
        $json = json_decode($response, true);

        SetValue($this->GetIDForIdent("Anlagenkennzeichen"), $json['DEVICE_INFO']['AKZ']);
        SetValue($this->GetIDForIdent("Ortskennzeichen"), $json['DEVICE_INFO']['OKZ']);

        // Beispielwert (für Test / Skalierung)
        //$wh = GetValue($this->GetIDForIdent("EnergyWh")) + rand(100, 500);

        //SetValue($this->GetIDForIdent("EnergyWh"), $wh);
        //SetValue($this->GetIDForIdent("EnergykWh"), round($wh / 1000, 3));

        // Periodenverbrauch (vereinfachtes Beispiel)
        //SetValue($this->GetIDForIdent("PeriodConsumptionkWh"), rand(5, 30));
    }
    
    // Überschreibt die interne IPS_ApplyChanges($id) Funktion
    public function ApplyChanges(): void
    {
        // Diese Zeile nicht löschen
        parent::ApplyChanges();
    
    }
}
