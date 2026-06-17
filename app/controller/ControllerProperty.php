<?php

namespace App\controller;

/**
 * Legacy facade for property/*, properties, featured and agency URLs.
 */
class ControllerProperty
{
    private ?ControllerPropertyCatalog $catalog = null;

    private function catalog(): ControllerPropertyCatalog
    {
        return $this->catalog ??= new ControllerPropertyCatalog();
    }

    public function index()
    {
        $this->catalog()->index();
    }

    public function properties()
    {
        $this->catalog()->properties();
    }

    public function show($id)
    {
        $this->catalog()->show($id);
    }

    public function agency($agencyUserId = null)
    {
        $this->catalog()->agency($agencyUserId);
    }

    public function featured()
    {
        $this->catalog()->featured();
    }

    public function owner($id)
    {
        $this->catalog()->owner($id);
    }


    private ?ControllerPropertyOwner $owner = null;

    private function ownerController(): ControllerPropertyOwner
    {
        return $this->owner ??= new ControllerPropertyOwner();
    }

    public function create()
    {
        $this->ownerController()->create();
    }

    public function store()
    {
        $this->ownerController()->store();
    }

    public function edit($id)
    {
        $this->ownerController()->edit($id);
    }

    public function update($id)
    {
        $this->ownerController()->update($id);
    }

    public function setStatus($id)
    {
        $this->ownerController()->setStatus($id);
    }

    public function requestDeletion($id)
    {
        $this->ownerController()->requestDeletion($id);
    }

    public function cancelDeletion($id)
    {
        $this->ownerController()->cancelDeletion($id);
    }

    public function requestBoost($id)
    {
        $this->ownerController()->requestBoost($id);
    }


    private ?ControllerPropertyEngagement $engagement = null;

    private function engagement(): ControllerPropertyEngagement
    {
        return $this->engagement ??= new ControllerPropertyEngagement();
    }

    public function favorite($id)
    {
        $this->engagement()->favorite($id);
    }

    public function unfavorite($id)
    {
        $this->engagement()->unfavorite($id);
    }

    public function affiliateRequest($id)
    {
        $this->engagement()->affiliateRequest($id);
    }

    public function getAffiliationTerms()
    {
        $this->engagement()->getAffiliationTerms();
    }


    private ?ControllerPropertyModeration $moderation = null;

    private function moderation(): ControllerPropertyModeration
    {
        return $this->moderation ??= new ControllerPropertyModeration();
    }

    public function moderate()
    {
        $this->moderation()->moderate();
    }

    public function startAnalysis($id)
    {
        $this->moderation()->startAnalysis($id);
    }

    public function approve($id)
    {
        $this->moderation()->approve($id);
    }

    public function reject($id)
    {
        $this->moderation()->reject($id);
    }

    public function approveBoost($id)
    {
        $this->moderation()->approveBoost($id);
    }

    public function rejectBoost($id)
    {
        $this->moderation()->rejectBoost($id);
    }
}
