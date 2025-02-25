<?php

class LDAPCollectionPlan extends CollectionPlan
{
    public function Init(): void
    {
        parent::Init();

        Orchestrator::AddRequirement('1.0.0', 'ldap');
    }

    /**
	 * @inheritdoc
	 */
	public function AddCollectorsToOrchestrator(): bool
	{
		Utils::Log(LOG_INFO, "---------- LDAP Collectors to launched ----------");

		return parent::AddCollectorsToOrchestrator();
	}
}
