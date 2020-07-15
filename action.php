<?php
/**
 * DokuWiki Plugin tos (Action Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Andreas Gohr <dokuwiki@cosmocode.de>
 */

class action_plugin_tos extends DokuWiki_Action_Plugin
{

    const FORMFIELD = 'plugin__tos_accept';

    /**
     * @inheritDoc
     */
    public function register(Doku_Event_Handler $controller)
    {
        $controller->register_hook('AUTH_LOGIN_CHECK', 'AFTER', $this, 'handleLogin');
        $controller->register_hook('HTML_LOGINFORM_OUTPUT', 'BEFORE', $this, 'handleLoginForm');

    }

    /**
     * Handle the login action
     *
     * Check if the TOCs have been accepted and update the state if needed
     *
     * @param Doku_Event $event event object by reference
     * @param mixed $param
     * @return void
     */
    public function handleLogin(Doku_Event $event, $param)
    {
        global $INPUT;
        if (!$event->result) return; // login failed anyway

        if ($INPUT->bool(self::FORMFIELD)) {
            // user accepted the TOCs right now, no further checks needed
            $this->userTosState($event->data['user'], true);
            return;
        }

        // ensure newest TOCs have been accepted before
        $newest = $this->newestTOS();
        if (!$newest) return; // we don't have a valid TOS, yet
        $accepted = $this->userTosState($event->data['user']);

        // fail the login when toc not accepted
        if ($accepted < $newest) {
            msg($this->getLang('acceptneeded'), -1);
            $event->result = false;
            auth_logoff();
            return;
        }
    }

    public function handleLoginForm(Doku_Event $event, $param)
    {
        /** @var Doku_Form $form */
        $form = $event->data;
        $form->insertElement(3, form_checkboxfield(['name' => self::FORMFIELD,'_class'=>'block', '_text' => $this->getLang('accept')]));
    }

    /**
     * Get the most recent, non-minor revision of the TOS page
     *
     * @return int
     * @todo maybe cache in session
     */
    protected function newestTOS()
    {
        $changes = new \dokuwiki\ChangeLog\PageChangeLog($this->getConf('tos'));
        if (!$changes->hasRevisions()) return 0;

        $start = 0;
        while ($revisions = $changes->getRevisions($start, 25)) {
            $start += count($revisions);
            foreach ($revisions as $rev) {
                $info = $changes->getRevisionInfo($rev);
                if ($info['type'] === 'E' || $info['type'] === 'C') return $rev;
            }
        }

        return 0;
    }

    /**
     * Check and optionally update the last TOS-Acceptance
     *
     * @param string $user the user to check/update
     * @param bool $accept set true is the user accepted the TOS right now
     * @return int the last acceptance of the TOS
     */
    protected function userTosState($user, $accept = false)
    {
        $tosfile = getCacheName($user, '.tos');

        if ($accept) {
            io_makeFileDir($tosfile);
            touch($tosfile);
        }

        return (int)@filemtime($tosfile);
    }
}

