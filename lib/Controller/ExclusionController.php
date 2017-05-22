<?php
/**
 * db       .d8b.  db   d8b   db d8888b. d88888b d8b   db  .o88b. d88888b
 * 88      d8' `8b 88   I8I   88 88  `8D 88'     888o  88 d8P  Y8 88'
 * 88      88ooo88 88   I8I   88 88oobY' 88ooooo 88V8o 88 8P      88ooooo
 * 88      88~~~88 Y8   I8I   88 88`8b   88~~~~~ 88 V8o88 8b      88~~~~~
 * 88booo. 88   88 `8b d8'8b d8' 88 `88. 88.     88  V888 Y8b  d8 88.
 * Y88888P YP   YP  `8b8' `8d8'  88   YD Y88888P VP   V8P  `Y88P' Y88888P
 *
 * Time: 10/4/2017 10:32
 * File Name: ExclusionController.php
 */

namespace OCA\Nextant\Controller;

use OCP\AppFramework\Controller;
use OCA\Nextant\Service\ConfigService;
use OCA\Nextant\Service\MiscService;
use OCA\Nextant\Db\ExclusionListMapper;
use OCP\IRequest;


class ExclusionController extends Controller
{
    private $userId;

    private $configService;

    private $miscService;

    private $exclusionListMapper;

    public function __construct($appName, IRequest $request, $userId, $configService, $miscService, $exclusionListMapper)
    {
        parent::__construct($appName, $request);

        $this->userId = $userId;

        $this->configService = $configService;

        $this->miscService = $miscService;

        $this->exclusionListMapper = $exclusionListMapper;
    }

    /**
     * @NoCSRFRequired
     * @PublicPage
     */
    public function getExclusionList($query){
        // init return
        $return = array(
            'query' => $query,
            'result' => array()
        );

        $return['result'] = $this->exclusionListMapper->findByUser($this->userId);

        return $return;
    }
}