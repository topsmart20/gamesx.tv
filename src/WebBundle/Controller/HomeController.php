<?php

namespace WebBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use AppBundle\Entity\Item;
use AppBundle\Entity\Support;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Validator\Constraints\NotBlank;

class HomeController extends Controller
{
    public function privacyAction() {
        $em = $this->getDoctrine()->getManager();
        $setting = $em->getRepository("AppBundle:Settings")->findOneBy(array(), array());
        return $this->render("WebBundle:Home:privacy_policy.html.twig", array("setting" => $setting));
    }
    public function refund_privacyAction() {
        $em = $this->getDoctrine()->getManager();
        $setting = $em->getRepository("AppBundle:Settings")->findOneBy(array(), array());
        return $this->render("WebBundle:Home:refund_policy.html.twig", array("setting" => $setting));
    }
    public function faqAction() {
        $em = $this->getDoctrine()->getManager();
        $setting = $em->getRepository("AppBundle:Settings")->findOneBy(array(), array());
        return $this->render("WebBundle:Home:faq.html.twig", array("setting" => $setting));
    }
    public function contactAction(Request $request) {

        $em=$this->getDoctrine()->getManager();
        $support = new Support();
        $form = $this->createFormBuilder($support)
            ->setMethod('POST')
            ->add('email', EmailType::class)
            ->add('subject', TextType::class)
            ->add('message', TextareaType::class)
            ->add('send', SubmitType::class,array("label"=>"Send Message"))
            ->getForm();
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($support);
            $em->flush();
            $this->addFlash('success', 'Operation has been done successfully');
            $support = new Support();
            $form = $this->createFormBuilder($support)
                ->setMethod('POST')
                ->add('email', EmailType::class)
                ->add('subject', TextType::class)
                ->add('message', TextareaType::class)
                ->add('send', SubmitType::class,array("label"=>"Send Message"))
                ->getForm();
        }

        return $this->render("WebBundle:Home:contact.html.twig", array( "form"=>$form->createView()));
    }
    public function ajaxMyListAction(Request $request)
    {
        $code = 500;
        if($request->isXmlHttpRequest()) {
            $em=$this->getDoctrine()->getManager();
            if ($this->getUser()!=null) {
                $id =$request->request->get('id');
                $type =$request->request->get('type');
                if ($type ==  "poster") {
                    $poster = $em->getRepository("AppBundle:Poster")->findOneBy(array("id"=>$id,"enabled"=>true));
                    if ($poster !=null) {
                        $item = $em->getRepository("AppBundle:Item")->findOneBy(array("user"=>$this->getUser(),"poster" => $poster));
                        if ($item == null) {
                            
                            $last_item = $em->getRepository("AppBundle:Item")->findOneBy(array("user"=>$this->getUser(),"channel" =>null),array("position"=>"desc"));
                            $position=1;
                            if ($last_item!=null) {
                                $position=$last_item->getPosition()+1;
                            }
                            $code = 200;
                            $item = new Item();
                            $item->setPoster($poster);
                            $item->setUser($this->getUser());
                            $item->setPosition($position);
                            $em->persist($item);
                            $em->flush();
                        }else{
                            $em->remove($item);
                            $em->flush();
                            $code = 202;
                        }
                    }
                }
                if ($type ==  "channel") {
                    $channel = $em->getRepository("AppBundle:Channel")->findOneBy(array("id"=>$id,"enabled"=>true));
                    if ($channel !=null) {
                        $item = $em->getRepository("AppBundle:Item")->findOneBy(array("user"=>$this->getUser(),"channel" => $channel));
                        if ($item == null) {
                            $last_item = $em->getRepository("AppBundle:Item")->findOneBy(array("user"=>$this->getUser(),"poster" =>null),array("position"=>"desc"));
                            $position=1;
                            if ($last_item!=null) {
                                $position=$last_item->getPosition()+1;
                            }


                            $code = 200;
                            $item = new Item();
                            $item->setChannel($channel);
                            $item->setUser($this->getUser());
                            $item->setPosition($position);
                            $em->persist($item);
                            $em->flush();
                        }else{
                            $em->remove($item);
                            $em->flush();
                            $code = 202;
                        }
                    }
                }
            }
        }
        return new Response($code);
    } 
    public function ajaxThemeAction(Request $request)
    {
        if($request->isXmlHttpRequest()) {
            $em=$this->getDoctrine()->getManager();
            $theme =$request->request->get('theme');
            if ($this->getUser()!=null) {
                $user = $em->getRepository("UserBundle:User")->findOneBy(array("id"=>$this->getUser()->getId()));
                $user->setTheme($theme);
                $em->flush();
            }

            $session = new Session();
            $session->set('theme', $theme);
        }
        return new Response("ok");

    }

    public function searchAction(Request $request){
        $em = $this->getDoctrine()->getManager();
        $q = " ";
        if ($request->query->has("q") and $request->query->get("q") != "") {
            $q .= " AND  p.title like '%" . $request->query->get("q") . "%'";
        }

        $dql = "SELECT p FROM AppBundle:Poster p  WHERE p.enabled = true " . $q . " ORDER BY p.created desc ";
        $query = $em->createQuery($dql);
        $paginator = $this->get('knp_paginator');
        $posters = $paginator->paginate(
            $query,
            $request->query->getInt('page', 1),
            42
        );

        $repository_channel = $em->getRepository('AppBundle:Channel');
        $repository_actor = $em->getRepository('AppBundle:Actor');
        $repository_game = $em->getRepository('AppBundle:Game');
        $repository_livechannel = $em->getRepository('AppBundle:Livechannel');
        $repository_feature = $em->getRepository('AppBundle:Feature');


        $query_channel = $repository_channel->createQueryBuilder('c')
            ->where("c.enabled = true","c.title like '%" . $request->query->get("q") . "%'")
            ->addOrderBy('c.created',"desc")
            ->addOrderBy('c.id', 'ASC')
            ->getQuery();
            
        $channels = $query_channel->getResult();

        $query_actor = $repository_actor->createQueryBuilder('c')
            ->where("UPPER(c.slug) like '%" . strtoupper($request->query->get("q")) . "%'")
            ->addOrderBy('c.name',"desc")
            ->addOrderBy('c.id', 'ASC')
            ->getQuery();

        $actors = $query_actor->getResult();

        $query_game = $repository_game->createQueryBuilder('c')
            ->where("c.title like '%" . $request->query->get("q") . "%'")
            ->addOrderBy('c.created', 'desc')
            ->addOrderBy('c.id', 'ASC')
            ->getQuery();

        $games = $query_game->getResult();

        $query_livechannel = $repository_livechannel->createQueryBuilder('c')
            ->where("c.name like '%" . $request->query->get("q") . "%'")
            ->addOrderBy('c.created', 'desc')
            ->addOrderBy('c.id', 'ASC')
            ->getQuery();

        $livechannels = $query_livechannel->getResult();

        $query_feature = $repository_feature->createQueryBuilder('c')
            ->where("c.name like '%" . $request->query->get("q") . "%'")
            ->addOrderBy('c.created', 'desc')
            ->addOrderBy('c.id', 'ASC')
            ->getQuery();

        $features = $query_feature->getResult();

        return $this->render('WebBundle:Home:search.html.twig',array(
            "channels"=>$channels,
            "posters"=>$posters,
            "actors"=>$actors,
            "games"=>$games,
            "livechannels"=>$livechannels,
            "features"=>$features,
            "episodes" =>array()
        ));
    }
    public function mylistAction(Request $request){

        $em = $this->getDoctrine()->getManager();

        $channels = $em->getRepository("AppBundle:Item")->findBy(array("poster"=>null,"user"=>$this->getUser()), array("position" => "desc"));
        $poster_size = sizeof($em->getRepository("AppBundle:Item")->findBy(array("channel"=>null), array("position" => "desc")));


        $repository = $em->getRepository('AppBundle:Item');


        $repo_query = $repository->createQueryBuilder('i');

        $repo_query->leftJoin('i.poster', 'p');
        $repo_query->leftJoin('i.channel', 'c');
        $repo_query->where($repo_query->expr()->isNotNull('i.poster'));
        $repo_query->andWhere("p.enabled = true");
        $repo_query->andWhere("i.user =".$this->getUser()->getId());


        $repo_query->addOrderBy('i.position', "desc");
        $repo_query->addOrderBy('p.id', 'ASC');

        $query =  $repo_query->getQuery(); 
        $paginator = $this->get('knp_paginator');
        $posters = $paginator->paginate(
            $query,
            $request->query->getInt('page', 1),
            42
        );
       return $this->render('WebBundle:Home:mylist.html.twig',array(
            "channels"=>$channels,
            "posters"=>$posters,
            "poster_size"=>$poster_size
        ));
    }
    public function indexAction()
    {
        $game_list = []; 
        $channel_list = [];
        $feature_list = [];

        $em = $this->getDoctrine()->getManager();

        $games=$em->getRepository('AppBundle:Game')->findAll();
        foreach ($games as $game) {
            $channel = $this->getChannelByQuery($game->getDescription());
            if (count($channel) > 0)
                $game_list[] = $game;
        }

        $channels=$em->getRepository('AppBundle:Livechannel')->findAll();
        foreach ($channels as $key => $channel) {
            $channelQ = $this->getChannelByQuery($channel->getName());
            if (count($channelQ) > 0)
                $channel_list[] = $channel;
        }

        $features=$em->getRepository('AppBundle:Feature')->findAll();
        foreach ($features as $key => $feature) {
            $channel = $this->getChannelByQuery($feature->getName());
            if (count($channel) > 0)
                $feature_list[] = $feature;
        }
        
        if ($this->getUser()!=null) {
          $user = $em->getRepository("UserBundle:User")->findOneBy(array("id"=>$this->getUser()->getId()));
          $session = new Session();
          $session->set('theme', $user->getTheme());
        }

        return $this->render('WebBundle:Home:index.html.twig',array(
            "games" => $game_list,
            "channels"=>$channel_list,
            "features"=>$feature_list
        ));
    }
    public function headerAction($subtitle,$og_type,$og_image,$keywords,$og_description) {
        $em = $this->getDoctrine()->getManager();
        $settings = $em->getRepository("AppBundle:Settings")->findOneBy(array(), array());
        return $this->render("WebBundle:Home:header.html.twig", array(
            "subtitle"=>$subtitle,
            "settings" => $settings,
            "og_type" => $og_type,
            "keywords" => $keywords,
            "og_description" => $og_description,
            "og_image"=>$og_image
        )
    );
    }
    public function logoAction() {
        $em = $this->getDoctrine()->getManager();
        $settings = $em->getRepository("AppBundle:Settings")->findOneBy(array(), array());
        return $this->render("WebBundle:Home:logo.html.twig", array("settings" => $settings));
    }
    public function gplayAction() {
        $em = $this->getDoctrine()->getManager();
        $settings = $em->getRepository("AppBundle:Settings")->findOneBy(array(), array());
        return $this->render("WebBundle:Home:gplay.html.twig", array("settings" => $settings));
    }

    private function getChannelByQuery($query) {
        $authorization = $this->container->getParameter('authorization');
        $client_id = $this->container->getParameter('client_id');

        $url = 'https://api.twitch.tv/helix/search/channels?';
        $headers = array(
            'Content-Type: application/json',
            'Authorization:' . $authorization,
            'Client-Id:' . $client_id
        );

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url.'query='.urlencode($query).'&live_only=true');
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        $body = '{}';
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
        curl_setopt($ch, CURLOPT_POSTFIELDS,$body);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $result = curl_exec($ch);
        
        if ($result === FALSE) {
            die('Curl failed: ' . curl_error($ch));
        }

        $channel = json_decode($result)->data;

        return $channel;
    }
}
