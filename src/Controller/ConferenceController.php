<?php

namespace App\Controller;

use Twig\Environment;
use App\Entity\Comment;
use App\Entity\Conference;
use App\Form\CommentFormType;
use Doctrine\ORM\EntityManager;
use App\Repository\CommentRepository;
use App\Repository\ConferenceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;

class ConferenceController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
    }
    
    
    // #[Route('/conference', name: 'app_conference')]
    #[Route('/', name: 'homepage')]
    // public function index(): Response
    // public function index(Environment $twig, ConferenceRepository $conferenceRepository): Response
    public function index(ConferenceRepository $conferenceRepository): Response
    {
        // return $this->render('conference/index.html.twig', [
        //     'controller_name' => 'ConferenceController',
        // ]);

        // return new Response(<<<EOF
        //            <html>
        //                <body>
        //                    <img src="/images/under-construction.gif" />
        //                </body>
        //            </html>
        //            EOF
        // );

        // return new Response($twig->render('conference/index.html.twig', [
        return $this->render('conference/index.html.twig', [
            'conferences' => $conferenceRepository->findAll(),
        ]);
    }

    // #[Route('/conference/{id}', name: 'conference')]
    #[Route('/conference/{slug}', name: 'conference')]
    // public function show(Environment $twig, Conference $conference, CommentRepository $commentRepository): Response
    // public function show(Request $request, Environment $twig, Conference $conference, CommentRepository $commentRepository): Response
    // public function show(Request $request, Conference $conference, CommentRepository $commentRepository): Response
    public function show(
        Request $request, 
        Conference $conference, 
        CommentRepository $commentRepository,
        #[Autowire('%photo_dir%')] string $photoDir,
        ): Response
    {
        // form de création de commentaire
        $comment = new Comment();
        $form = $this->createForm(CommentFormType::class, $comment);
        
        // gestion du form
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $comment->setConference(($conference));

            // upload de la photo
            if ($photo = $form['photo']->getData()) {
                $filename = bin2hex(random_bytes(6)).'.'.$photo->guessExtension();
                try {
                    $photo->move($photoDir, $filename);
                } catch (FileException $e) {
                    // unable to upload the photo, give up
                }
                $comment->setPhotoFilename($filename);
            }

            $this->entityManager->persist($comment);
            $this->entityManager->flush();

            return $this->redirectToRoute('conference', ['slug' => $conference->getSlug()]);
        }
        
        $offset = max(0, $request->query->getInt('offset', 0));
        $paginator = $commentRepository->getCommentPaginator($conference, $offset);
        
        // return new Response($twig->render('conference/show.html.twig', [
        return $this->render('conference/show.html.twig', [
            'conference' => $conference,
            // 'comments' => $commentRepository->findBy(['conference' => $conference], ['createdAt' => 'DESC']),

            'comments' => $paginator,
            'previous' => $offset - CommentRepository::PAGINATOR_PER_PAGE,
            'next' => min(count($paginator), $offset + CommentRepository::PAGINATOR_PER_PAGE),
            'comment_form' => $form,
        ]);

    }
}
