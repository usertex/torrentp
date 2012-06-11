<?php

namespace SOTB\CoreBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Request;

use SOTB\CoreBundle\TorrentResponse;

class TorrentController extends Controller
{
    /**
     * @Template()
     */
    public function listAction(Request $request)
    {
        $dm = $this->container->get('doctrine.odm.mongodb.document_manager');

        $query = $dm->getRepository('SOTBCoreBundle:Torrent')->getAll();

        $paginator = $this->get('knp_paginator');
        $pagination = $paginator->paginate(
            $query,
            $this->get('request')->query->get('page', 1) /*page number*/,
            2/*limit per page*/
        );

        return compact('pagination');
    }

    /**
     * @Template()
     */
    public function showAction($slug)
    {
        $dm = $this->container->get('doctrine.odm.mongodb.document_manager');

        $torrent = $dm->getRepository('SOTBCoreBundle:Torrent')->findOneBy(array('slug' => $slug));

        if (null === $torrent) {
            throw $this->createNotFoundException();
        }

        return array('torrent' => $torrent);
    }

    /**
     * @Template()
     */
    public function uploadAction(Request $request)
    {
        $torrent = new \SOTB\CoreBundle\Document\Torrent();
        $torrent->setUploader($this->getUser());

        $form = $this->createForm(new \SOTB\CoreBundle\Form\Type\TorrentFormType(), $torrent);

        if ('POST' === $request->getMethod()) {
            $form->bindRequest($request);
            if ($form->isValid()) {
                $dm = $this->container->get('doctrine.odm.mongodb.document_manager');

                $torrentManager = $this->container->get('torrent_manager');
                $torrentManager->upload($torrent);

                $dm->persist($torrent);
                $dm->flush();

                return $this->redirect($this->generateUrl('torrent', array('slug' => $torrent->getSlug())));
            }
        }

        return array(
            'form' => $form->createView()
        );
    }

    public function downloadAction($slug)
    {
        $dm = $this->container->get('doctrine.odm.mongodb.document_manager');

        $torrent = $dm->getRepository('SOTBCoreBundle:Torrent')->findOneBy(array('slug' => $slug));

        if (null === $torrent) {
            throw $this->createNotFoundException();
        }

        $torrentManager = $this->container->get('torrent_manager');

        return new TorrentResponse($torrent, $torrentManager->getUploadRootDir());
    }

}
