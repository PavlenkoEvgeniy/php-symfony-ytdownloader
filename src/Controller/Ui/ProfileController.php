<?php

declare(strict_types=1);

namespace App\Controller\Ui;

use App\Entity\User;
use App\Form\ChangePasswordForm;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class ProfileController extends AbstractController
{
    #[Route('/ui/profile', name: 'ui_profile_index')]
    #[IsGranted('ROLE_USER')]
    public function index(Request $request, EntityManagerInterface $em, UserPasswordHasherInterface $passwordHasher): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $form = $this->createForm(ChangePasswordForm::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Check current password
            $currentPassword = $form->get('currentPassword')->getData();
            if (!$passwordHasher->isPasswordValid($user, $currentPassword)) {
                $this->addFlash('error', 'The current password is incorrect.');

                return $this->redirectToRoute('ui_profile_index');
            }

            // Encode and set the new password
            $newPassword    = $form->get('newPassword')->getData();
            $hashedPassword = $passwordHasher->hashPassword($user, $newPassword);
            $user->setPassword($hashedPassword);

            $em->persist($user);
            $em->flush();

            $this->addFlash('success', 'Your password was successfully updated.');

            return $this->redirectToRoute('ui_profile_index');
        }

        return $this->render('ui/profile/index.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}
