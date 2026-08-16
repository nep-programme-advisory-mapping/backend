<?php

namespace Database\Seeders;

use App\Models\ActivityCategory;
use App\Models\ActivityItem;
use App\Models\ActivitySubcategory;
use Illuminate\Database\Seeder;

class TaxonomySeeder extends Seeder
{
    public function run(): void
    {
        $taxonomy = [
            'B1' => [
                'label' => 'Support to Learners',
                'subcategories' => [
                    'B1.1' => [
                        'label' => 'Financial support',
                        'items' => [
                            'B1.1.01' => ['label' => 'Scholarships', 'is_other' => false],
                            'B1.1.02' => ['label' => 'Conditional cash transfers or attendance incentives', 'is_other' => false],
                            'B1.1.03' => ['label' => 'Family income support linked to enrolment or retention', 'is_other' => false],
                            'B1.1.04' => ['label' => 'Household livelihood support as an education retention enabler', 'is_other' => false],
                            'B1.1.05' => ['label' => 'Other (please specify)', 'is_other' => true],
                        ]
                    ],
                    'B1.2' => [
                        'label' => 'Material support',
                        'items' => [
                            'B1.2.01' => ['label' => 'Uniforms, books, school supplies', 'is_other' => false],
                            'B1.2.02' => ['label' => 'Assistive devices for learners with disabilities', 'is_other' => false],
                            'B1.2.03' => ['label' => 'Bicycles or transport equipment', 'is_other' => false],
                            'B1.2.04' => ['label' => 'Other (please specify)', 'is_other' => true],
                        ]
                    ],
                    'B1.3' => [
                        'label' => 'Logistical support',
                        'items' => [
                            'B1.3.01' => ['label' => 'Transport services', 'is_other' => false],
                            'B1.3.02' => ['label' => 'Boarding and dormitory provision', 'is_other' => false],
                            'B1.3.03' => ['label' => 'Meal provision', 'is_other' => false],
                            'B1.3.04' => ['label' => 'Other (please specify)', 'is_other' => true],
                        ]
                    ],
                    'B1.4' => [
                        'label' => 'Psychosocial and wellbeing support (within education context)',
                        'items' => [
                            'B1.4.01' => ['label' => 'Counselling services', 'is_other' => false],
                            'B1.4.02' => ['label' => 'Safe space programmes', 'is_other' => false],
                            'B1.4.03' => ['label' => 'Mental health support', 'is_other' => false],
                            'B1.4.04' => ['label' => 'Other (please specify)', 'is_other' => true],
                        ]
                    ],
                    'B1.5' => [
                        'label' => 'Learning support',
                        'items' => [
                            'B1.5.01' => ['label' => 'Remedial or catch-up classes', 'is_other' => false],
                            'B1.5.02' => ['label' => 'After-school or extracurricular academic support', 'is_other' => false],
                            'B1.5.03' => ['label' => 'Mother-tongue or multilingual learning support', 'is_other' => false],
                            'B1.5.04' => ['label' => 'Individual tutoring or mentoring of learners', 'is_other' => false],
                            'B1.5.05' => ['label' => 'Other (please specify)', 'is_other' => true],
                        ]
                    ],
                    'B1.6' => [
                        'label' => 'Access and enrolment',
                        'items' => [
                            'B1.6.01' => ['label' => 'First-time enrolment support and awareness campaigns', 'is_other' => false],
                            'B1.6.02' => ['label' => 'Identification and outreach to children never enrolled', 'is_other' => false],
                            'B1.6.03' => ['label' => 'Identification and outreach to out-of-school children and youth', 'is_other' => false],
                            'B1.6.04' => ['label' => 'Transition support between education levels', 'is_other' => false],
                            'B1.6.05' => ['label' => 'Re-entry into formal education after dropout', 'is_other' => false],
                            'B1.6.06' => ['label' => 'Equivalency and accelerated learning programmes', 'is_other' => false],
                            'B1.6.07' => ['label' => 'Other (please specify)', 'is_other' => true],
                        ]
                    ],
                ]
            ],
            'B2' => [
                'label' => 'Support to Teachers',
                'subcategories' => [
                    'B2.1' => [
                        'label' => 'Initial teacher education',
                        'items' => [
                            'B2.1.01' => ['label' => 'Pre-service training delivered at a Teacher Education Institution', 'is_other' => false],
                            'B2.1.02' => ['label' => 'Practicum support and supervision', 'is_other' => false],
                            'B2.1.03' => ['label' => 'Student teacher scholarships or stipends', 'is_other' => false],
                            'B2.1.04' => ['label' => 'Other (please specify)', 'is_other' => true],
                        ]
                    ],
                    'B2.2' => [
                        'label' => 'Continuous professional development',
                        'items' => [
                            'B2.2.01' => ['label' => 'Structured CPD programmes (face-to-face)', 'is_other' => false],
                            'B2.2.02' => ['label' => 'Online or blended CPD delivery', 'is_other' => false],
                            'B2.2.03' => ['label' => 'Subject-specific content training', 'is_other' => false],
                            'B2.2.04' => ['label' => 'Pedagogical skills training', 'is_other' => false],
                            'B2.2.05' => ['label' => 'Inclusive education methodology', 'is_other' => false],
                            'B2.2.06' => ['label' => 'Training mainstream teachers for inclusive classrooms', 'is_other' => false],
                            'B2.2.07' => ['label' => 'Early grade reading or numeracy methodology', 'is_other' => false],
                            'B2.2.08' => ['label' => 'Digital literacy for teachers', 'is_other' => false],
                            'B2.2.09' => ['label' => 'English language training for teachers', 'is_other' => false],
                            'B2.2.10' => ['label' => 'Teacher professional identity and reflective practice programmes', 'is_other' => false],
                            'B2.2.11' => ['label' => 'Library management training for school librarians', 'is_other' => false],
                            'B2.2.12' => ['label' => 'Other (please specify)', 'is_other' => true],
                        ]
                    ],
                    'B2.3' => [
                        'label' => 'External coaching',
                        'items' => [
                            'B2.3.01' => ['label' => 'In-classroom coaching by NGO-deployed coaches', 'is_other' => false],
                            'B2.3.02' => ['label' => 'Observation and feedback systems', 'is_other' => false],
                            'B2.3.03' => ['label' => 'Other (please specify)', 'is_other' => true],
                        ]
                    ],
                    'B2.4' => [
                        'label' => 'Peer and school-based learning',
                        'items' => [
                            'B2.4.01' => ['label' => 'Peer coaching facilitation', 'is_other' => false],
                            'B2.4.02' => ['label' => 'Teacher learning circles or professional learning communities', 'is_other' => false],
                            'B2.4.03' => ['label' => 'Mentoring by senior teachers', 'is_other' => false],
                            'B2.4.04' => ['label' => 'Establishment of teacher development centres or professional learning spaces', 'is_other' => false],
                            'B2.4.05' => ['label' => 'Other (please specify)', 'is_other' => true],
                        ]
                    ],
                    'B2.5' => [
                        'label' => 'Teacher educator development',
                        'items' => [
                            'B2.5.01' => ['label' => 'Training of trainers at Teacher Education Institutions', 'is_other' => false],
                            'B2.5.02' => ['label' => 'Curriculum development support for TEIs', 'is_other' => false],
                            'B2.5.03' => ['label' => 'Practicum reform and supervision capacity at TEIs', 'is_other' => false],
                            'B2.5.04' => ['label' => 'Systematic capacity transfer with planned handover to government institutions', 'is_other' => false],
                            'B2.5.05' => ['label' => 'Other (please specify)', 'is_other' => true],
                        ]
                    ],
                    'B2.6' => [
                        'label' => 'Teacher professional standing and welfare',
                        'items' => [
                            'B2.6.01' => ['label' => 'Teacher welfare support programmes', 'is_other' => false],
                            'B2.6.02' => ['label' => 'Professional association strengthening', 'is_other' => false],
                            'B2.6.03' => ['label' => 'Advocacy for teacher status, conditions, or career pathways', 'is_other' => false],
                            'B2.6.04' => ['label' => 'Other (please specify)', 'is_other' => true],
                        ]
                    ],
                ]
            ],
            'B3' => [
                'label' => 'Support to Schools as Institutions',
                'subcategories' => [
                    'B3.1' => [
                        'label' => 'Leadership and governance',
                        'items' => [
                            'B3.1.01' => ['label' => 'Training for school directors and deputy directors', 'is_other' => false],
                            'B3.1.02' => ['label' => 'School improvement planning', 'is_other' => false],
                            'B3.1.03' => ['label' => 'School-based management strengthening', 'is_other' => false],
                            'B3.1.04' => ['label' => 'Child protection and safeguarding systems at school level', 'is_other' => false],
                            'B3.1.05' => ['label' => 'Other (please specify)', 'is_other' => true],
                        ]
                    ],
                    'B3.2' => [
                        'label' => 'Community and parental engagement',
                        'items' => [
                            'B3.2.01' => ['label' => 'Parent association strengthening', 'is_other' => false],
                            'B3.2.02' => ['label' => 'Community mobilisation for education', 'is_other' => false],
                            'B3.2.03' => ['label' => 'Village or commune education committee support', 'is_other' => false],
                            'B3.2.04' => ['label' => 'Volunteer programme facilitation', 'is_other' => false],
                            'B3.2.05' => ['label' => 'Caregiver and parenting education for pre-school age children', 'is_other' => false],
                            'B3.2.06' => ['label' => 'Child and youth clubs and councils', 'is_other' => false],
                            'B3.2.07' => ['label' => 'Other (please specify)', 'is_other' => true],
                        ]
                    ],
                    'B3.3' => [
                        'label' => 'School health',
                        'items' => [
                            'B3.3.01' => ['label' => 'School feeding and nutrition programmes', 'is_other' => false],
                            'B3.3.02' => ['label' => 'Health screening and referral services', 'is_other' => false],
                            'B3.3.03' => ['label' => 'Hygiene promotion and WASH programmes', 'is_other' => false],
                            'B3.3.04' => ['label' => 'Other (please specify)', 'is_other' => true],
                        ]
                    ],
                    'B3.4' => [
                        'label' => 'Physical environment',
                        'items' => [
                            'B3.4.01' => ['label' => 'Classroom construction or rehabilitation', 'is_other' => false],
                            'B3.4.02' => ['label' => 'Sanitation and water facilities', 'is_other' => false],
                            'B3.4.03' => ['label' => 'Electricity and solar power provision', 'is_other' => false],
                            'B3.4.04' => ['label' => 'Library or resource room development', 'is_other' => false],
                            'B3.4.05' => ['label' => 'ICT infrastructure (hardware and connectivity)', 'is_other' => false],
                            'B3.4.06' => ['label' => 'Community digital access and technology infrastructure', 'is_other' => false],
                            'B3.4.07' => ['label' => 'Other (please specify)', 'is_other' => true],
                        ]
                    ],
                    'B3.5' => [
                        'label' => 'Materials and equipment',
                        'items' => [
                            'B3.5.01' => ['label' => 'Textbooks and curriculum materials', 'is_other' => false],
                            'B3.5.02' => ['label' => 'Teaching and learning materials', 'is_other' => false],
                            'B3.5.03' => ['label' => 'Laboratory or STEM equipment', 'is_other' => false],
                            'B3.5.04' => ['label' => 'Library and reading materials', 'is_other' => false],
                            'B3.5.05' => ['label' => 'Adapted learning materials and assistive technology for learners with disabilities', 'is_other' => false],
                            'B3.5.06' => ['label' => 'Local language children\'s book development and publishing', 'is_other' => false],
                            'B3.5.07' => ['label' => 'Digital reading platforms and online learning resources', 'is_other' => false],
                            'B3.5.08' => ['label' => 'Mobile library and outreach learning materials delivery', 'is_other' => false],
                            'B3.5.09' => ['label' => 'Other (please specify)', 'is_other' => true],
                        ]
                    ],
                    'B3.6' => [
                        'label' => 'Operating support',
                        'items' => [
                            'B3.6.01' => ['label' => 'Direct operational funding or school grants', 'is_other' => false],
                            'B3.6.02' => ['label' => 'School financial management support', 'is_other' => false],
                            'B3.6.03' => ['label' => 'Other (please specify)', 'is_other' => true],
                        ]
                    ],
                    'B3.7' => [
                        'label' => 'Programme delivery within school',
                        'items' => [
                            'B3.7.01' => ['label' => 'Extracurricular classes (general subjects)', 'is_other' => false],
                            'B3.7.02' => ['label' => 'Extracurricular English language classes', 'is_other' => false],
                            'B3.7.03' => ['label' => 'Extracurricular after-school academic support', 'is_other' => false],
                            'B3.7.04' => ['label' => 'In-school arts, sport or cultural activities', 'is_other' => false],
                            'B3.7.05' => ['label' => 'In-school STEM or digital activities', 'is_other' => false],
                            'B3.7.06' => ['label' => 'In-school life skills and leadership activities', 'is_other' => false],
                            'B3.7.07' => ['label' => 'Other (please specify)', 'is_other' => true],
                        ]
                    ],
                ]
            ],
            'B4' => [
                'label' => 'Support to Education System and Governance',
                'subcategories' => [
                    'B4.1' => [
                        'label' => 'Government institutional capacity',
                        'items' => [
                            'B4.1.01' => ['label' => 'Capacity building of MoEYS national departments', 'is_other' => false],
                            'B4.1.02' => ['label' => 'Capacity building of Provincial Offices of Education', 'is_other' => false],
                            'B4.1.03' => ['label' => 'Capacity building of District Offices of Education', 'is_other' => false],
                            'B4.1.04' => ['label' => 'Support to Teacher Education Institutions as institutions', 'is_other' => false],
                            'B4.1.05' => ['label' => 'Technical support to government-operated institutions following programme transfer', 'is_other' => false],
                            'B4.1.06' => ['label' => 'Systematic capacity transfer with planned handover to government institutions', 'is_other' => false],
                            'B4.1.07' => ['label' => 'Other (please specify)', 'is_other' => true],
                        ]
                    ],
                    'B4.2' => [
                        'label' => 'Planning and information systems',
                        'items' => [
                            'B4.2.01' => ['label' => 'Education management information systems', 'is_other' => false],
                            'B4.2.02' => ['label' => 'School mapping and planning support', 'is_other' => false],
                            'B4.2.03' => ['label' => 'Budget planning and financial management', 'is_other' => false],
                            'B4.2.04' => ['label' => 'Other (please specify)', 'is_other' => true],
                        ]
                    ],
                    'B4.3' => [
                        'label' => 'Policy, standards and advocacy',
                        'items' => [
                            'B4.3.01' => ['label' => 'Policy research and evidence generation', 'is_other' => false],
                            'B4.3.02' => ['label' => 'Development of standards, frameworks or guidelines', 'is_other' => false],
                            'B4.3.03' => ['label' => 'Initiating, leading or co-leading a policy working group', 'is_other' => false],
                            'B4.3.04' => ['label' => 'Advocacy on specific policy issues', 'is_other' => false],
                            'B4.3.05' => ['label' => 'Public awareness campaigns and media engagement', 'is_other' => false],
                            'B4.3.06' => ['label' => 'Social accountability and citizen engagement with service providers', 'is_other' => false],
                            'B4.3.07' => ['label' => 'Other (please specify)', 'is_other' => true],
                        ]
                    ],
                ]
            ],
            'B5' => [
                'label' => 'Direct Education Provision',
                'subcategories' => [
                    'B5.1' => [
                        'label' => 'Formal education',
                        'items' => [
                            'B5.1.01' => ['label' => 'Operating a registered pre-primary centre', 'is_other' => false],
                            'B5.1.02' => ['label' => 'Operating a registered primary or secondary school', 'is_other' => false],
                            'B5.1.03' => ['label' => 'Operating a registered Teacher Education Institution', 'is_other' => false],
                            'B5.1.04' => ['label' => 'Operating a registered TVET centre', 'is_other' => false],
                            'B5.1.05' => ['label' => 'Operating a registered higher education institution', 'is_other' => false],
                            'B5.1.06' => ['label' => 'Other (please specify)', 'is_other' => true],
                        ]
                    ],
                    'B5.2' => [
                        'label' => 'Non-formal and complementary education',
                        'items' => [
                            'B5.2.01' => ['label' => 'Community learning centre operation', 'is_other' => false],
                            'B5.2.02' => ['label' => 'Lifelong learning programme delivery', 'is_other' => false],
                            'B5.2.03' => ['label' => 'Adult literacy class delivery', 'is_other' => false],
                            'B5.2.04' => ['label' => 'Income-generating and vocational skills programmes', 'is_other' => false],
                            'B5.2.05' => ['label' => 'Other (please specify)', 'is_other' => true],
                        ]
                    ],
                    'B5.3' => [
                        'label' => 'Extracurricular and enrichment (standalone, outside school)',
                        'items' => [
                            'B5.3.01' => ['label' => 'Arts, sport or cultural programmes', 'is_other' => false],
                            'B5.3.02' => ['label' => 'STEM or digital clubs', 'is_other' => false],
                            'B5.3.03' => ['label' => 'Leadership and life skills programmes', 'is_other' => false],
                            'B5.3.04' => ['label' => 'Environmental education programmes', 'is_other' => false],
                            'B5.3.05' => ['label' => 'Cultural heritage and traditional arts education', 'is_other' => false],
                            'B5.3.06' => ['label' => 'English language classes (standalone, outside school)', 'is_other' => false],
                            'B5.3.07' => ['label' => 'Other (please specify)', 'is_other' => true],
                        ]
                    ],
                    'B5.4' => [
                        'label' => 'TVET and youth employment pathways',
                        'items' => [
                            'B5.4.01' => ['label' => 'Career orientation and guidance', 'is_other' => false],
                            'B5.4.02' => ['label' => 'TVET access and awareness campaigns', 'is_other' => false],
                            'B5.4.03' => ['label' => 'Vocational skills training (registered programme)', 'is_other' => false],
                            'B5.4.04' => ['label' => 'Vocational skills training (non-registered or informal)', 'is_other' => false],
                            'B5.4.05' => ['label' => 'English language and communication training', 'is_other' => false],
                            'B5.4.06' => ['label' => 'Entrepreneurship training and support', 'is_other' => false],
                            'B5.4.07' => ['label' => 'Job placement and employer linkage', 'is_other' => false],
                            'B5.4.08' => ['label' => 'Internship and work placement facilitation', 'is_other' => false],
                            'B5.4.09' => ['label' => 'Investor readiness and pitch preparation', 'is_other' => false],
                            'B5.4.10' => ['label' => 'Private sector partnership for youth employment', 'is_other' => false],
                            'B5.4.11' => ['label' => 'Networking events and professional community-building', 'is_other' => false],
                            'B5.4.12' => ['label' => 'Financial literacy training', 'is_other' => false],
                            'B5.4.13' => ['label' => 'Transitional housing and integration support for graduates entering employment', 'is_other' => false],
                            'B5.4.14' => ['label' => 'Other (please specify)', 'is_other' => true],
                        ]
                    ],
                ]
            ],
            'B6' => [
                'label' => 'Community Health and Nutrition',
                'subcategories' => [
                    'B6.1' => [
                        'label' => 'Community health and nutrition activities',
                        'items' => [
                            'B6.1.01' => ['label' => 'Maternal and child health programmes', 'is_other' => false],
                            'B6.1.02' => ['label' => 'Nutrition and malnutrition prevention', 'is_other' => false],
                            'B6.1.03' => ['label' => 'Health screening and referral for children and families', 'is_other' => false],
                            'B6.1.04' => ['label' => 'Immunisation and disease prevention', 'is_other' => false],
                            'B6.1.05' => ['label' => 'Community health worker training', 'is_other' => false],
                            'B6.1.06' => ['label' => 'Water, sanitation and hygiene (community level, not school-based)', 'is_other' => false],
                            'B6.1.07' => ['label' => 'Child safety programmes (drowning prevention, road safety, and similar)', 'is_other' => false],
                            'B6.1.08' => ['label' => 'Other (please specify)', 'is_other' => true],
                        ]
                    ],
                ]
            ],
            'B7' => [
                'label' => 'Child Protection and Reintegration',
                'subcategories' => [
                    'B7.1' => [
                        'label' => 'Child protection and reintegration activities',
                        'items' => [
                            'B7.1.01' => ['label' => 'Reintegration of children from exploitative situations (trafficking, labour, prostitution)', 'is_other' => false],
                            'B7.1.02' => ['label' => 'Support for street children and homeless youth', 'is_other' => false],
                            'B7.1.03' => ['label' => 'Support for orphans and children without family care', 'is_other' => false],
                            'B7.1.04' => ['label' => 'Legal aid and documentation support for children and families', 'is_other' => false],
                            'B7.1.05' => ['label' => 'Psychosocial support for children affected by violence or exploitation', 'is_other' => false],
                            'B7.1.06' => ['label' => 'Family tracing, reunification and reintegration support', 'is_other' => false],
                            'B7.1.07' => ['label' => 'Family livelihood and economic support', 'is_other' => false],
                            'B7.1.08' => ['label' => 'Other (please specify)', 'is_other' => true],
                        ]
                    ],
                ]
            ],
            'B8' => [
                'label' => 'Livelihoods and Food Security',
                'subcategories' => [
                    'B8.1' => [
                        'label' => 'Livelihoods and food security activities',
                        'items' => [
                            'B8.1.01' => ['label' => 'Sustainable agriculture and farming support', 'is_other' => false],
                            'B8.1.02' => ['label' => 'Food security and nutrition-sensitive agriculture', 'is_other' => false],
                            'B8.1.03' => ['label' => 'Agricultural value chain development', 'is_other' => false],
                            'B8.1.04' => ['label' => 'Micro-enterprise and micro-franchise support', 'is_other' => false],
                            'B8.1.05' => ['label' => 'Savings groups and financial inclusion', 'is_other' => false],
                            'B8.1.06' => ['label' => 'Other (please specify)', 'is_other' => true],
                        ]
                    ],
                ]
            ],
            'B9' => [
                'label' => 'Emergency and Crisis Response',
                'subcategories' => [
                    'B9.1' => [
                        'label' => 'Emergency and crisis response activities',
                        'items' => [
                            'B9.1.01' => ['label' => 'Emergency education (temporary learning spaces, accelerated learning in crisis)', 'is_other' => false],
                            'B9.1.02' => ['label' => 'Psychosocial support in emergency settings', 'is_other' => false],
                            'B9.1.03' => ['label' => 'Emergency child protection', 'is_other' => false],
                            'B9.1.04' => ['label' => 'Emergency health and nutrition response', 'is_other' => false],
                            'B9.1.05' => ['label' => 'Non-food item and shelter support', 'is_other' => false],
                            'B9.1.06' => ['label' => 'Emergency WASH', 'is_other' => false],
                            'B9.1.07' => ['label' => 'Other (please specify)', 'is_other' => true],
                        ]
                    ],
                ]
            ],
        ];

        foreach ($taxonomy as $catCode => $catData) {
            $category = ActivityCategory::updateOrCreate(
                ['code' => $catCode],
                ['label' => $catData['label'], 'active' => true]
            );

            foreach ($catData['subcategories'] as $subCode => $subData) {
                $subcategory = ActivitySubcategory::updateOrCreate(
                    ['code' => $subCode],
                    ['category_id' => $category->id, 'label' => $subData['label'], 'active' => true]
                );

                foreach ($subData['items'] as $itemCode => $itemData) {
                    ActivityItem::updateOrCreate(
                        ['code' => $itemCode],
                        [
                            'subcategory_id' => $subcategory->id,
                            'label' => $itemData['label'],
                            'active' => true,
                            'is_other' => $itemData['is_other']
                        ]
                    );
                }
            }
        }
    }
}
