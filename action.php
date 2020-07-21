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
        #$controller->register_hook('AUTH_LOGIN_CHECK', 'AFTER', $this, 'handleLogin');
        #$controller->register_hook('HTML_LOGINFORM_OUTPUT', 'BEFORE', $this, 'handleLoginForm');

        $controller->register_hook('ACTION_ACT_PREPROCESS', 'BEFORE', $this, 'checkTosAccept');
        $controller->register_hook('TPL_ACT_UNKNOWN', 'BEFORE', $this, 'showTos');
    }

    /**
     * Check if the TOCs have been accepted and update the state if needed
     *
     * @param Doku_Event $event event object by reference
     * @param mixed $param
     * @return void
     */
    public function checkTosAccept(Doku_Event $event, $param)
    {
        global $INPUT;
        $user = $INPUT->server->str('REMOTE_USER');
        if ($user === '') return;
        $act = act_clean($event->data);
        if ($act === 'logout') return;
        // FIXME skip for admins and superusers

        // if user accepted the TOCs right now, no further checks needed
        if ($INPUT->bool(self::FORMFIELD)) {
            $this->userTosState($user, true);
            return;
        }

        // ensure newest TOCs have been accepted before
        $newest = $this->newestTOS();
        if (!$newest) return; // we don't have a valid TOS, yet
        $accepted = $this->userTosState($user);
        if ($accepted >= $newest) return;

        $event->data = 'plugin_tos';
    }

    /**
     * Display the TOS and ask to accept
     *
     * @param Doku_Event $event event object by reference
     * @param mixed $param
     * @return void
     */
    public function showTos(Doku_Event $event, $param)
    {
        global $ID;
        global $INPUT;

        if ($event->data !== 'plugin_tos') return;
        $event->preventDefault();


        echo '<div class="plugin-tos">';
        echo $this->locale_xhtml('intro');

        $accepted = $this->userTosState($INPUT->server->str('REMOTE_USER'));
        if($accepted) {
            echo '<label for="plugin__tos_showdiff">';
            echo sprintf($this->getLang('showdiff'), dformat($accepted, '%f'));
            echo '</label>';
            echo '<input type="checkbox" id="plugin__tos_showdiff">';
            echo $this->diffTos($accepted);
        }

        echo '<div class="tos-content">';
        echo p_wiki_xhtml($this->getConf('tos'));
        echo '</div>';

        echo '<ul class="tos-form">';
        echo '<li class="tos-nope"><a href="' . wl($ID,
                ['do' => 'logout']) . '">' . $this->getLang('nope') . '</a></li>';
        echo '<li class="tos-accept"><a href="' . wl($ID,
                [self::FORMFIELD => '1']) . '">' . $this->getLang('accept') . '</a></li>';
        echo '</ul>';

        echo '</div>';
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

        $start = -1; // also check current revision
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
            io_saveFile($tosfile, $user);
        }

        return (int)@filemtime($tosfile);
    }

    /**
     * Create a inline diff of changes since the last accept
     *
     * @param int $lastaccept when the TOS was last accepted
     * @return string
     */
    protected function diffTos($lastaccept) {
        $change = new \dokuwiki\ChangeLog\PageChangeLog($this->getConf('tos'));
        $oldrev = $change->getLastRevisionAt($lastaccept);
        $old = rawWiki($this->getConf('tos'), $oldrev);
        $new = rawWiki($this->getConf('tos'));
        $diff = new Diff(explode("\n", $old), explode("\n", $new));
        $formatter = new InlineDiffFormatter();

        $html  = '<div class="table tos-diff">';
        $html  .= '<table class="diff diff_inline">';
        $html .= html_insert_softbreaks($formatter->format($diff));
        $html .= '</table>';
        $html .= '</div>';

        return $html;
    }


}

