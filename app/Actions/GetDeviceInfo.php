<?php

namespace App\Actions;

class GetDeviceInfo
{
    protected $agent;
    protected $request;

    public function __construct(Agent $agent, Request $request)
    {
        $this->agent = $agent;
        $this->request = $request;
    }

    public function handle(): array
    {
        return [
            'user_agent' => $this->request->userAgent(),
            'ip' => $this->request->ip(),
            'device' => $this->agent->device(),
            'platform' => $this->agent->platform(),
            'browser' => $this->agent->browser(),
            'browser_version' => $this->agent->version($this->agent->browser()),
            'is_mobile' => $this->agent->isMobile(),
            'is_tablet' => $this->agent->isTablet(),
            'is_desktop' => $this->agent->isDesktop(),
            'is_robot' => $this->agent->isRobot(),
            'device_type' => $this->getDeviceType(),
        ];
    }

    public function isMobile()
    {
        return $this->agent->isMobile();
    }

    public function isTablet()
    {
        return $this->agent->isTablet();
    }

    public function isDesktop()
    {
        return $this->agent->isDesktop();
    }

    private function getDeviceType()
    {
        if ($this->agent->isMobile()) return 'mobile';
        if ($this->agent->isTablet()) return 'tablet';
        if ($this->agent->isDesktop()) return 'desktop';
        return 'unknown';
    }
}
