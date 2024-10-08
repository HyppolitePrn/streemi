<?php

namespace App\DataFixtures;

use App\Entity\Category;
use App\Entity\Episode;
use App\Entity\Language;
use App\Entity\Media;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

use App\Entity\Movie;
use App\Entity\Season;
use App\Entity\Serie;
use App\Entity\Subscription;
use App\Entity\SubscriptionHistory;
use App\Entity\User;
use App\Entity\Comment;
use App\Entity\Playlist;
use App\Entity\PlaylistMedia;
use App\Entity\PlaylistSubscription;
use App\Entity\WatchHistory;
use App\Enum\CommentStatusEnum;
use App\Enum\UserAccountStatusEnum;

class AppFixtures extends Fixture
{
    private \Faker\Generator $faker;
    private $toPersist = [];

    public function load(ObjectManager $manager): void
    {
        $this->faker = \Faker\Factory::create();

        for ($i = 0; $i < 10; $i++) {
            $this->createCategory();
        }

        for ($i = 0; $i < 10; $i++) {
            $this->createUser();
        }

        foreach ($this->toPersist as $entity) {
            $manager->persist($entity);
        }

        $manager->flush();
    }

    // User related
    private $subscriptions = [];
    private function getSubscriptions(): array
    {
        if (empty($this->subscriptions)) {
            $subscription = new Subscription();
            $subscription->setName('Basic');
            $subscription->setPrice(0);
            $subscription->setDuration(1);
            $this->subscriptions[] = $subscription;

            $subscription = new Subscription();
            $subscription->setName('Premium');
            $subscription->setPrice(9.99);
            $subscription->setDuration(1);
            $this->subscriptions[] = $subscription;

            $subscription = new Subscription();
            $subscription->setName('Premium Plus');
            $subscription->setPrice(14.99);
            $subscription->setDuration(1);
            $this->subscriptions[] = $subscription;

            foreach ($this->subscriptions as $subscription) {
                $this->toPersist[] = $subscription;
            }
        }

        return $this->subscriptions;
    }

    private function createUser(): User
    {
        $user = new User();
        $user->setEmail($this->faker->email());
        $user->setPassword($this->faker->password()); // TODO: hash password
        $user->setAccountStatus($this->faker->randomElement(UserAccountStatusEnum::cases()));
        $user->setUsername($this->faker->userName());

        $this->toPersist[] = $user;

        $userSubscriptionHistories = $this->createUserSubscriptionHistory();
        foreach ($userSubscriptionHistories as $userSubscriptionHistory) {
            $user->addSubscriptionHistory($userSubscriptionHistory);
            $userSubscriptionHistory->setSubscriber($user);
        }

        for ($i = 0; $i < $this->faker->numberBetween(0, 5); $i++) {
            $comment = $this->createComment();
            $comment->setPublisher($user);
            $user->addComment($comment);
        }

        for ($i = 0; $i < $this->faker->numberBetween(0, 5); $i++) {
            $playlist = $this->createUserPlaylist();
            $playlist->setCreator($user);
            $user->addPlaylist($playlist);
        }

        for ($i = 0; $i < $this->faker->numberBetween(0, 5); $i++) {
            $playlistSubscription = $this->createUserPlaylistSubscription();
            $playlistSubscription->setSubscriber($user);
            $user->addPlaylistSubscription($playlistSubscription);
        }

        for ($i = 0; $i < $this->faker->numberBetween(0, 100); $i++) {
            $watchHistory = $this->createUserWatchHistory();
            $watchHistory->setWatcher($user);
            $user->addWatchHistory($watchHistory);
        }

        return $user;
    }

    private function createUserSubscriptionHistory(): array
    {
        $createdUserSubscriptionHistories = [];

        $subscriptions = $this->getSubscriptions();
        
        $numberOfSubscriptions = $this->faker->numberBetween(-5, 5);
        $numberOfSubscriptions = $numberOfSubscriptions < 0 ? 0 : $numberOfSubscriptions;

        $startAt = $this->faker->dateTimeBetween('-5 year', 'now');
        for ($i = 0; $i < $numberOfSubscriptions; $i++) {
            $subscriptionHistory = new SubscriptionHistory();
            $subscription = $subscriptions[$this->faker->numberBetween(0, count($subscriptions) - 1)];
            $subscriptionHistory->setSubscription($subscription);
            $subscriptionHistory->setStartAt(\DateTimeImmutable::createFromMutable($startAt));
            $endAt = \DateTimeImmutable::createFromMutable($startAt);
            $endAt->modify(sprintf('+%d month', $subscription->getDuration()));
            $subscriptionHistory->setEndAt($endAt);

            $this->toPersist[] = $subscriptionHistory;

            $createdUserSubscriptionHistories[] = $subscriptionHistory;

            $startAt = \DateTime::createFromImmutable($endAt);
        }

        return $createdUserSubscriptionHistories;
    }

    private function createComment(bool $recursive = false): Comment
    {
        $comment = new Comment();
        $comment->setContent($this->faker->sentence(10));
        $comment->setStatus($this->faker->randomElement(CommentStatusEnum::cases()));
        
        if (!$recursive) {
            for ($i = 0; $i < 3; $i++) {
                $childComment = $this->createComment(true);
                $comment->setParentComment($childComment);
                $childComment->addChildComment($comment);
            }
        }

        $medias = $this->getToPersistMedia();
        $comment->setMedia($medias[$this->faker->numberBetween(0, count($medias) - 1)]);

        $this->toPersist[] = $comment;

        return $comment;
    }

    private function createUserPlaylist(): Playlist
    {
        $playlist = new Playlist();
        $playlist->setName($this->faker->sentence(3));
        $createdAt = $this->faker->dateTimeBetween('-1 year', 'now');
        $playlist->setCreatedAt(\DateTimeImmutable::createFromMutable($createdAt));
        $playlist->setUpdatedAt(\DateTimeImmutable::createFromMutable($createdAt));
        
        $this->toPersist[] = $playlist;

        $medias = $this->getToPersistMedia();
        for ($i = 0; $i < 5; $i++) {
            $media = $medias[$this->faker->numberBetween(0, count($medias) - 1)];
            $playlistMedium = new PlaylistMedia();
            $playlistMedium->setAddedAt(\DateTimeImmutable::createFromMutable($this->faker->dateTimeBetween('-1 year', 'now')));
            $playlistMedium->setMedia($media);

            $this->toPersist[] = $playlistMedium;

            $playlist->addPlaylistMedium($playlistMedium);
        }

        return $playlist;
    }

    private function getToPersistPlaylist(): array
    {
        $playlists = [];
        foreach ($this->toPersist as $entity) {
            if ($entity instanceof Playlist) {
                $playlists[] = $entity;
            }
        }

        return $playlists;
    }

    private function createUserPlaylistSubscription(): PlaylistSubscription
    {
        $playlistSubscription = new PlaylistSubscription();
        $playlistSubscription->setSubscribedAt(\DateTimeImmutable::createFromMutable($this->faker->dateTimeBetween('-1 year', 'now')));

        $this->toPersist[] = $playlistSubscription;

        $playlists = $this->getToPersistPlaylist();
        $playlistSubscription->setPlaylist($playlists[$this->faker->numberBetween(0, count($playlists) - 1)]);

        return $playlistSubscription;
    }

    private function createUserWatchHistory(): WatchHistory
    {
        $medias = $this->getToPersistMedia();
        
        $watchHistory = new WatchHistory();
        $watchHistory->setLastWatchedAt(\DateTimeImmutable::createFromMutable($this->faker->dateTimeBetween('-1 year', 'now')));
        $watchHistory->setNumberOfViews($this->faker->numberBetween(1, 10));

        $this->toPersist[] = $watchHistory;

        $media = $medias[$this->faker->numberBetween(0, count($medias) - 1)];
        $watchHistory->setMedia($media);

        return $watchHistory;
    }

    // Media related
    private function getToPersistMedia(): array
    {
        $medias = [];
        foreach ($this->toPersist as $entity) {
            if ($entity instanceof Media) {
                $medias[] = $entity;
            }
        }

        return $medias;
    }

    private $languages = [];
    private function getLanguages(): array
    {
        if (empty($this->languages)) {
            $this->languages = [
                (new Language())->setCode('en')->setName('English'),
                (new Language())->setCode('fr')->setName('French'),
                (new Language())->setCode('es')->setName('Spanish'),
                (new Language())->setCode('de')->setName('German'),
                (new Language())->setCode('it')->setName('Italian'),
                (new Language())->setCode('pt')->setName('Portuguese'),
                (new Language())->setCode('ru')->setName('Russian'),
                (new Language())->setCode('ja')->setName('Japanese'),
                (new Language())->setCode('zh')->setName('Chinese'),
                (new Language())->setCode('ar')->setName('Arabic'),
            ];

            foreach ($this->languages as $language) {
                $this->toPersist[] = $language;
            }
        }

        return $this->languages;
    }

    private function setMediaProperties(Media $media): void
    {
        $media->setTitle($this->faker->sentence(3));
        $media->setShortDescription($this->faker->sentence(10));
        $media->setLongDescription($this->faker->sentence(30));
        $media->setReleaseDate($this->faker->dateTimeBetween('-30 years', 'now'));
        $media->setCoverImage($this->faker->imageUrl());
        $media->setStaff([
            'director' => $this->faker->name(),
            'producer' => $this->faker->name(),
            'screenwriter' => $this->faker->name(),
        ]);
        $media->setCasting([
            $this->faker->name(),
            $this->faker->name(),
            $this->faker->name(),
        ]);

        $languages = $this->getLanguages();
        $randomLanguages = $this->faker->randomElements($languages, 3);
        foreach ($randomLanguages as $language) {
            $media->addLanguage($language);
        }
    }

    private function createMovie(): Movie
    {
        $movie = new Movie();
        $this->setMediaProperties($movie);

        $this->toPersist[] = $movie;

        return $movie;
    }

    private function createEpisode(): Episode
    {
        $episode = new Episode();
        $episode->setTitle($this->faker->sentence(3));
        $episode->setDuration(\DateTimeImmutable::createFromFormat('H:i:s', $this->faker->time('H:i:s')));
        $episode->setReleasedAt(\DateTimeImmutable::createFromMutable($this->faker->dateTimeBetween('-1 year', 'now')));

        $this->toPersist[] = $episode;

        return $episode;
    }

    private function createSeason(int $seasonNumber): Season
    {
        $season = new Season();
        $season->setNumber($seasonNumber);

        $this->toPersist[] = $season;

        for ($i = 0; $i < 10; $i++) {
            $episode = $this->createEpisode();
            $season->addEpisode($episode);
            $episode->setSeason($season);
        }

        return $season;
    }

    private function createSerie(): Serie
    {
        $serie = new Serie();
        $this->setMediaProperties($serie);

        $this->toPersist[] = $serie;

        for ($i = 0; $i < 5; $i++) {
            $season = $this->createSeason($i + 1);
            $serie->addSeason($season);
            $season->setSerie($serie);
        }

        return $serie;
    }

    private function createCategory(): Category
    {
        $category = new Category();
        $name = $this->faker->word();
        $category->setName(lcfirst($name));
        $category->setLabel(ucfirst($name));

        $this->toPersist[] = $category;

        for ($i = 0; $i < 5; $i++) {
            $movie = $this->createMovie();
            $category->addMedia($movie);
            $movie->addCategory($category);

            $serie = $this->createSerie();
            $category->addMedia($serie);
            $serie->addCategory($category);
        }

        return $category;
    }
}