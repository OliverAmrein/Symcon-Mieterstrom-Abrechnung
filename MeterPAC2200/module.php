<?php

class MeterPAC2200 extends IPSModule
{
    public function Create()
    {
        parent::Create();

        // Zuordnung
        $this->RegisterPropertyInteger("TenantID", 0);
        $this->RegisterPropertyFloat("SharePercent", 100);

        // Messwerte
        $this->RegisterVariableFloat("EnergyWh", "Energie gesamt (Wh)");
        $this->RegisterVariableFloat("EnergykWh", "Energie gesamt (kWh)");
        $this->RegisterVariableFloat("PeriodConsumptionkWh", "Periodenverbrauch (kWh)");

        // Polling
        $this->RegisterTimer("Update", 300000, 'PAC_Update($_IPS["TARGET"]);');
    }

    public function Update()
    {
        // Beispielwert (für Test / Skalierung)
        $wh = GetValue($this->GetIDForIdent("EnergyWh")) + rand(100, 500);

        SetValue($this->GetIDForIdent("EnergyWh"), $wh);
        SetValue($this->GetIDForIdent("EnergykWh"), round($wh / 1000, 3));

        // Periodenverbrauch (vereinfachtes Beispiel)
        SetValue($this->GetIDForIdent("PeriodConsumptionkWh"), rand(5, 30));
    }
}
