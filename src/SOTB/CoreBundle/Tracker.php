<?php

namespace SOTB\CoreBundle;

use Doctrine\ODM\MongoDB\DocumentManager;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;

use SOTB\CoreBundle\Tracker\AnnounceResponse;
use SOTB\CoreBundle\Document\Peer;
use SOTB\CoreBundle\Document\Torrent;

/**
 * @author Matt Drollette <matt@drollette.com>
 */
class Tracker
{
    private $dm;

    public function __construct(DocumentManager $dm)
    {
        $this->dm = $dm;
    }

    public function announce(ParameterBag $params)
    {
        if (
            !$params->has('info_hash') ||
            !$params->has('peer_id') ||
            !$params->has('port') ||
            !$params->has('uploaded') ||
            !$params->has('downloaded') ||
            !$params->has('left')
        ) {
            return $this->announceFailure("Invalid get parameters.");
        }

        // validate the request
        if (20 != strlen($params->get('info_hash'))) {
            return $this->announceFailure("Invalid length of info_hash.");
        }
        if (20 != strlen($params->get('peer_id'))) {
            return $this->announceFailure("Invalid length of info_hash.");
        }
        if (!(is_numeric($params->getInt('port')) && is_int($params->getInt('port') + 0) && 0 <= $params->getInt('port'))) {
            return $this->announceFailure("Invalid port value.");
        }
        if (!(is_numeric($params->getInt('uploaded')) && is_int($params->getInt('uploaded') + 0) && 0 <= $params->getInt('uploaded'))) {
            return $this->announceFailure("Invalid uploaded value.");
        }
        if (!(is_numeric($params->getInt('downloaded')) && is_int($params->getInt('downloaded') + 0) && 0 <= $params->getInt('downloaded'))) {
            return $this->announceFailure("Invalid downloaded value.");
        }
        if (!(is_numeric($params->getInt('left')) && is_int($params->getInt('left') + 0) && 0 <= $params->getInt('left'))) {
            return $this->announceFailure("Invalid left value.");
        }

        $torrent = $this->dm->getRepository('SOTBCoreBundle:Torrent')->findOneBy(array('hash' => $params->get('info_hash')));

        if (null === $torrent) {
            return $this->announceFailure('Invalid info hash.');
        }

        $peer = $this->dm->getRepository('SOTBCoreBundle:Peer')->findOneBy(array('peerId' => $params->get('peer_id')));

        if (null === $peer) {
            $peer = new Peer();
            $peer->setPeerId($params->get('peer_id'));
        }

        if ('completed' === $params->getInt('event')) {
            $peer->setComplete(true);
        }

        $peer->setTorrent($torrent);
        $peer->setIp($params->get('ip'));
        $peer->setPort($params->get('port'));
        $peer->setDownloaded($params->getInt('downloaded'));
        $peer->setUploaded($params->getInt('uploaded'));
        $peer->setLeft($params->getInt('left'));

        $configInterval = '10';
        $interval = $configInterval + mt_rand(round($configInterval / -10), round($configInterval / 10));

        // If the client gracefully exists, we set its ttl to 0, double-interval otherwise.
        $peer->setInterval(('completed' === $params->get('event'))? 0 : $interval * 2);

        $this->dm->persist($peer);

        try {
            $peers = $this->getPeers($torrent, $peer, $params->get('compact', false), $params->get('no_peer_id', false));
            $peer_stats = $this->getPeerStats($torrent, $peer);
        } catch (\Exception $e) {
            return $this->announceFailure($e->getMessage());
        }


        $response = array(
            'interval'      => $interval,
            'complete'      => intval($peer_stats['complete']),
            'incomplete'    => intval($peer_stats['incomplete']),
            'peers'         => $peers,
        );

        $this->dm->flush();

        return new AnnounceResponse($response);
    }

    protected function getPeers(Torrent $torrent, Peer $peer, $compact = false, $no_peer_id = false)
    {
        if ($compact) {
            $return = '';
            foreach ($torrent->getActivePeers() as $aPeer) {
                if ($peer->getPeerId() !== $peer->$aPeer()) {
                    $return .= pack('N', ip2long($aPeer->getIp()));
                    $return .= pack('n', intval($aPeer->getPort()));
                }
            }
        } else {
            $return = array();
            foreach ($torrent->getActivePeers() as $aPeer) {
                if ($peer->getPeerId() !== $aPeer->getPeerId()) {
                    $result = array(
                        'ip'        => $aPeer->getIp(),
                        'port'      => $aPeer->getPort(),
                    );
                    if (!$no_peer_id) {
                        $result['peer id'] = $aPeer->getPeerId();
                    }
                    $return[] = $result;
                }
            }
        }

        return $return;
    }

    protected function getPeerStats(Torrent $torrent, Peer $peer)
    {
        $result = array('complete' => 0, 'incomplete' => 0);

        foreach ($torrent->getActivePeers() as $aPeer) {
            if ($peer->getPeerId() !== $aPeer->getPeerId()) {
                if ($aPeer->isComplete()) {
                    $result['complete'] += 1;
                } else {
                    $result['incomplete'] += 1;
                }
            }
        }

        return $result;
    }

    protected function announceFailure($msg)
    {
        return new AnnounceResponse(array('failure reason' => $msg));
    }
}