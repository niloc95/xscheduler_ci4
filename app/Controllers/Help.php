<?php

/**
 * =============================================================================
 * HELP CENTER CONTROLLER
 * =============================================================================
 * 
 * @file        app/Controllers/Help.php
 * @description Help center with FAQs, documentation, support resources,
 *              and context-sensitive help for users.
 * 
 * ROUTES HANDLED:
 * -----------------------------------------------------------------------------
 * GET  /help                         : Help center home
 * GET  /help/faq                     : Frequently asked questions
 * GET  /help/article/:slug           : View help article
 * GET  /help/search                  : Search help content
 * GET  /help/contact                 : Contact support form
 * POST /help/contact                 : Submit support request
 * GET  /help/tour                    : Interactive feature tour
 * 
 * PURPOSE:
 * -----------------------------------------------------------------------------
 * Provides self-service support resources:
 * - Searchable FAQ database
 * - Role-specific help articles (admin, provider, staff)
 * - Getting started guides
 * - Video tutorials (if configured)
 * - Contact form for support tickets
 * - Interactive product tour
 * 
 * CONTENT ORGANIZATION:
 * -----------------------------------------------------------------------------
 * - Getting Started: Setup guides, first steps
 * - Appointments: Booking, rescheduling, cancellation
 * - User Management: Roles, permissions, schedules
 * - Settings: Configuration options explained
 * - Troubleshooting: Common issues and solutions
 * 
 * ACCESS CONTROL:
 * -----------------------------------------------------------------------------
 * - Public: Basic FAQs and general help
 * - Authenticated: Role-specific content and support tickets
 * 
 * @see         app/Views/help/ for view templates
 * @package     App\Controllers
 * @extends     BaseController
 * @author      WebSchedulr Team
 * @copyright   2024-2026 WebSchedulr
 * =============================================================================
 */

namespace App\Controllers;

use App\Models\UserModel;
use CodeIgniter\Controller;

class Help extends BaseController
{
    protected $userModel;

    public function __construct()
    {
        $this->userModel = new UserModel();
        helper('permissions');
    }

    /**
     * Display help center
     */
    public function index()
    {
        // Help center is accessible to everyone, but shows different content for logged-in users
        $isLoggedIn = session()->get('isLoggedIn');
        $currentUser = $isLoggedIn ? session()->get('user') : null;
        $currentRole = $isLoggedIn ? current_user_role() : 'guest';

        $data = [
            'title' => 'Help Center',
            'current_page' => 'help',
            'user_role' => $currentRole,
            'user' => $currentUser,
            'is_logged_in' => $isLoggedIn,
            'faqs' => $this->getFaqs(),
            'popular_articles' => $this->getPopularArticles($currentRole)
        ];

        return view('help/index', $data);
    }

    /**
     * Display specific help article
     */
    public function article($articleId = null)
    {
        if (!session()->get('isLoggedIn')) {
            return redirect()->to('/auth/login');
        }

        if (!$articleId) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Article not found');
        }

        $article = $this->getHelpArticle($articleId);
        
        if (!$article) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Article not found');
        }

        $data = [
            'title' => $article['title'],
            'current_page' => 'help',
            'article' => $article,
            'related_articles' => $this->getRelatedArticles($article['category'], $articleId)
        ];

        return view('help/article', $data);
    }

    /**
     * Display help category
     */
    public function category($categorySlug = null)
    {
        if (!session()->get('isLoggedIn')) {
            return redirect()->to('/auth/login');
        }

        if (!$categorySlug) {
            return redirect()->to('/help');
        }

        $category = $this->getHelpCategory($categorySlug);
        $articles = $this->getArticlesByCategory($categorySlug);

        $data = [
            'title' => $category['name'] . ' - Help',
            'current_page' => 'help',
            'category' => $category,
            'articles' => $articles
        ];

        return view('help/category', $data);
    }

    /**
     * Search help articles
     */
    public function search()
    {
        // Search is accessible to everyone
        $isLoggedIn = session()->get('isLoggedIn');
        $currentUser = $isLoggedIn ? session()->get('user') : null;
        $currentRole = $isLoggedIn ? current_user_role() : 'guest';

        $query = $this->request->getGet('query') ?? $this->request->getGet('q');
        $results = [];

        if ($query) {
            $results = $this->searchHelpArticles($query);
        }

        $data = [
            'title' => 'Search Help',
            'current_page' => 'help',
            'user_role' => $currentRole,
            'user' => $currentUser,
            'is_logged_in' => $isLoggedIn,
            'query' => $query,
            'results' => $results,
            'result_count' => count($results)
        ];

        return view('help/search', $data);
    }

    /**
     * Contact support
     */
    public function contact()
    {
        if (!session()->get('isLoggedIn')) {
            return redirect()->to('/auth/login');
        }

        $data = [
            'title' => 'Contact Support',
            'current_page' => 'help',
            'support_options' => $this->getSupportOptions(),
            'validation' => session()->getFlashdata('validation')
        ];

        return view('help/contact', $data);
    }

    /**
     * Submit support ticket
     */
    public function submitTicket()
    {
        if (!session()->get('isLoggedIn')) {
            return redirect()->to('/auth/login');
        }

        $rules = [
            'subject' => 'required|min_length[5]|max_length[200]',
            'category' => 'required',
            'priority' => 'required|in_list[low,medium,high,urgent]',
            'message' => 'required|min_length[20]|max_length[2000]'
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()
                ->withInput()
                ->with('validation', $this->validator);
        }

        // In real implementation, save ticket to database and send notifications
        
        return redirect()->to('/help')->with('success', 'Support ticket submitted successfully. We\'ll get back to you within 24 hours.');
    }

    /**
     * FAQ section
     */
    public function faq()
    {
        if (!session()->get('isLoggedIn')) {
            return redirect()->to('/auth/login');
        }

        $data = [
            'title' => 'Frequently Asked Questions',
            'current_page' => 'help',
            'faq_categories' => $this->getFAQCategories(),
            'user_role' => current_user_role()
        ];

        return view('help/faq', $data);
    }

    /**
     * Video tutorials
     */
    public function tutorials()
    {
        if (!session()->get('isLoggedIn')) {
            return redirect()->to('/auth/login');
        }

        $data = [
            'title' => 'Video Tutorials',
            'current_page' => 'help',
            'tutorial_categories' => $this->getTutorialCategories(),
            'featured_tutorials' => $this->getFeaturedTutorials()
        ];

        return view('help/tutorials', $data);
    }

    /**
     * Get help categories based on user role
     */
    private function getHelpCategories($role)
    {
        $baseCategories = [
            [
                'id' => 1,
                'name' => 'Getting Started',
                'slug' => 'getting-started',
                'description' => 'Basic setup and account information',
                'icon' => 'play-circle',
                'article_count' => 8,
                'color' => 'blue'
            ],
            [
                'id' => 2,
                'name' => 'Booking & Appointments',
                'slug' => 'appointments',
                'description' => 'How to book, manage, and modify appointments',
                'icon' => 'calendar',
                'article_count' => 12,
                'color' => 'green'
            ],
            [
                'id' => 3,
                'name' => 'Account & Profile',
                'slug' => 'account',
                'description' => 'Managing your account settings and profile',
                'icon' => 'user',
                'article_count' => 6,
                'color' => 'purple'
            ]
        ];

        if (has_role(['admin', 'provider', 'staff'])) {
            $baseCategories = array_merge($baseCategories, [
                [
                    'id' => 4,
                    'name' => 'Service Management',
                    'slug' => 'services',
                    'description' => 'Adding and managing services',
                    'icon' => 'clipboard-list',
                    'article_count' => 9,
                    'color' => 'amber'
                ],
                [
                    'id' => 5,
                    'name' => 'Calendar & Scheduling',
                    'slug' => 'scheduling',
                    'description' => 'Managing schedules and availability',
                    'icon' => 'calendar-alt',
                    'article_count' => 15,
                    'color' => 'indigo'
                ]
            ]);
        }

        if (has_role(['admin', 'provider'])) {
            $baseCategories = array_merge($baseCategories, [
                [
                    'id' => 6,
                    'name' => 'Analytics & Reports',
                    'slug' => 'analytics',
                    'description' => 'Understanding reports and analytics',
                    'icon' => 'chart-bar',
                    'article_count' => 7,
                    'color' => 'red'
                ],
                [
                    'id' => 7,
                    'name' => 'User Management',
                    'slug' => 'users',
                    'description' => 'Managing staff and customer accounts',
                    'icon' => 'users',
                    'article_count' => 10,
                    'color' => 'pink'
                ]
            ]);
        }

        if (is_admin()) {
            $baseCategories[] = [
                'id' => 8,
                'name' => 'System Settings',
                'slug' => 'system',
                'description' => 'Configuration and system administration',
                'icon' => 'cog',
                'article_count' => 14,
                'color' => 'gray'
            ];
        }

        return $baseCategories;
    }

    /**
     * Get popular help articles
     */
    private function getPopularArticles($role)
    {
        $articles = [
            [
                'id' => 1,
                'title' => 'How to Book Your First Appointment',
                'slug' => 'how-to-book-first-appointment',
                'category' => 'Getting Started',
                'views' => 1250,
                'helpful' => 95
            ],
            [
                'id' => 2,
                'title' => 'Managing Your Schedule',
                'slug' => 'managing-your-schedule',
                'category' => 'Calendar & Scheduling',
                'views' => 890,
                'helpful' => 88
            ],
            [
                'id' => 3,
                'title' => 'Understanding Appointment Status',
                'slug' => 'understanding-appointment-status',
                'category' => 'Appointments',
                'views' => 675,
                'helpful' => 92
            ],
            [
                'id' => 4,
                'title' => 'Updating Your Profile Information',
                'slug' => 'updating-profile-information',
                'category' => 'Account',
                'views' => 523,
                'helpful' => 89
            ]
        ];

        if (has_role(['admin', 'provider', 'staff'])) {
            $articles = array_merge($articles, [
                [
                    'id' => 5,
                    'title' => 'Adding New Services',
                    'slug' => 'adding-new-services',
                    'category' => 'Service Management',
                    'views' => 445,
                    'helpful' => 91
                ],
                [
                    'id' => 6,
                    'title' => 'Setting Your Availability',
                    'slug' => 'setting-your-availability',
                    'category' => 'Scheduling',
                    'views' => 398,
                    'helpful' => 87
                ]
            ]);
        }

        return array_slice($articles, 0, 6);
    }

    /**
     * Get recent help updates
     */
    private function getRecentUpdates()
    {
        return [
            [
                'title' => 'New Mobile App Features',
                'date' => '2025-09-01',
                'type' => 'feature'
            ],
            [
                'title' => 'Updated Booking Process',
                'date' => '2025-08-28',
                'type' => 'update'
            ],
            [
                'title' => 'Payment Integration Guide',
                'date' => '2025-08-25',
                'type' => 'new'
            ]
        ];
    }

    /**
     * Get specific help article
     */
    private function getHelpArticle($id)
    {
        // Mock article data - in real implementation, fetch from database
        $articles = [
            1 => [
                'id' => 1,
                'title' => 'How to Book Your First Appointment',
                'category' => 'getting-started',
                'content' => '<p>Welcome to WebSchedulr! Booking your first appointment is easy...</p>',
                'last_updated' => '2025-08-15',
                'helpful_yes' => 95,
                'helpful_no' => 5,
                'views' => 1250
            ]
            // Add more articles as needed
        ];

        return $articles[$id] ?? null;
    }

    /**
     * Get help category
     */
    private function getHelpCategory($slug)
    {
        $categories = [
            'getting-started' => [
                'name' => 'Getting Started',
                'slug' => 'getting-started',
                'description' => 'Basic setup and account information'
            ]
            // Add more categories
        ];

        return $categories[$slug] ?? ['name' => 'Category', 'slug' => $slug, 'description' => ''];
    }

    /**
     * Get articles by category
     */
    private function getArticlesByCategory($categorySlug)
    {
        // Mock data - in real implementation, fetch from database
        return [
            [
                'id' => 1,
                'title' => 'How to Book Your First Appointment',
                'excerpt' => 'Learn the basics of booking appointments...',
                'views' => 1250,
                'helpful' => 95
            ]
            // Add more articles
        ];
    }

    /**
     * Search help articles
     */
    private function searchHelpArticles($query)
    {
        // Mock search results - in real implementation, search database
        return [
            [
                'id' => 1,
                'title' => 'How to Book Your First Appointment',
                'excerpt' => 'Welcome to WebSchedulr! Booking your first appointment is easy...',
                'category' => 'Getting Started',
                'relevance' => 95
            ]
        ];
    }

    /**
     * Get related articles
     */
    private function getRelatedArticles($category, $excludeId)
    {
        // Mock related articles
        return [
            [
                'id' => 2,
                'title' => 'Managing Your Appointments',
                'category' => 'Getting Started'
            ]
        ];
    }

    /**
     * Get support options
     */
    private function getSupportOptions()
    {
        return [
            'email' => 'support@webschedulr.com',
            'phone' => '+1 (555) 123-4567',
            'hours' => 'Monday - Friday, 9:00 AM - 6:00 PM EST',
            'response_time' => 'Within 24 hours',
            'live_chat' => false
        ];
    }

    /**
     * Get FAQ categories
     */
    private function getFAQCategories()
    {
        return [
            [
                'name' => 'General',
                'questions' => [
                    [
                        'question' => 'How do I reset my password?',
                        'answer' => 'You can reset your password by clicking the "Forgot Password" link on the login page.'
                    ],
                    [
                        'question' => 'Can I cancel an appointment?',
                        'answer' => 'Yes, you can cancel appointments up to 24 hours before the scheduled time.'
                    ]
                ]
            ],
            [
                'name' => 'Billing',
                'questions' => [
                    [
                        'question' => 'What payment methods do you accept?',
                        'answer' => 'We accept all major credit cards, PayPal, and cash payments.'
                    ]
                ]
            ]
        ];
    }

    /**
     * Get tutorial categories
     */
    private function getTutorialCategories()
    {
        return [
            [
                'name' => 'Getting Started',
                'tutorials' => [
                    [
                        'title' => 'WebSchedulr Overview',
                        'duration' => '5:30',
                        'thumbnail' => '/assets/images/tutorial-1.jpg'
                    ]
                ]
            ]
        ];
    }

    /**
     * Get featured tutorials
     */
    private function getFeaturedTutorials()
    {
        return [
            [
                'title' => 'Complete Setup Guide',
                'duration' => '12:45',
                'views' => 2340,
                'thumbnail' => '/assets/images/featured-1.jpg'
            ]
        ];
    }

    /**
     * Get FAQs for help center
     */
    private function getFaqs()
    {
        return [
            [
                'question' => 'How do I book an appointment?',
                'answer' => 'You can book an appointment by navigating to the Appointments section and clicking "Book New Appointment". Select your preferred service, date, and time from the available slots.'
            ],
            [
                'question' => 'Can I cancel or reschedule my appointment?',
                'answer' => 'Yes, you can cancel or reschedule appointments up to 24 hours before the scheduled time. Go to your appointments list and use the "Reschedule" or "Cancel" options.'
            ],
            [
                'question' => 'What payment methods do you accept?',
                'answer' => 'We accept all major credit cards (Visa, MasterCard, American Express), PayPal, and cash payments at the time of service.'
            ],
            [
                'question' => 'How do I reset my password?',
                'answer' => 'Click the "Forgot Password" link on the login page, enter your email address, and follow the instructions sent to your email to reset your password.'
            ],
            [
                'question' => 'Can I update my profile information?',
                'answer' => 'Yes, you can update your profile information by going to the Profile section and clicking "Edit Profile". You can change your name, email, phone number, and other details.'
            ],
            [
                'question' => 'How do I contact customer support?',
                'answer' => 'You can contact our support team through the Help section by clicking "Contact Support", using our live chat feature, or calling our support phone number during business hours.'
            ],
            [
                'question' => 'What are your business hours?',
                'answer' => 'Our standard business hours are Monday through Friday, 9:00 AM to 6:00 PM, and Saturday 10:00 AM to 4:00 PM. Some services may have extended hours.'
            ],
            [
                'question' => 'Do you offer group appointments?',
                'answer' => 'Yes, we offer group appointments for certain services. Please contact us directly to discuss group booking options and availability.'
            ]
        ];
    }
}
