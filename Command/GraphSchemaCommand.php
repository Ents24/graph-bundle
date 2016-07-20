<?php

namespace Adadgio\GraphBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Finder\Finder;

use Adadgio\GraphBundle\ORM\Cypher;
use Adadgio\GraphBundle\Annotation\GraphAnnotationReader;

class GraphSchemaCommand extends ContainerAwareCommand
{
    /**
     * @var object \Doctrine\ORM\EntityManager
     */
    private $em;

    /**
     * @var object \Adadgio\GraphBundle\Component\Ne4jManager
     */
    private $neo4j;

    /**
     * @var string Directories to find entities from.
     */
    private $kernelDir;

    /**
     * Command constructor.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Configure command.
     *
     * @return void
     */
    protected function configure()
    {
        $this
            ->setName('adadgio:graph:schema')
            ->setDescription('Updates Neo4j graph database schema')
            ->addArgument(
                'action',
                InputArgument::REQUIRED,
                'The action to perform'
            )
        ;
    }

    /**
     * Execute command.
     *
     * @param object \InputInterface
     * @param object \OutputInterface
     * @return void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $container = $this->getContainer();

        $this->em = $container->get('doctrine')->getManager();
        $this->neo4j = $container->get('adadgio_graph.neo4j_manager');
        $this->kernelDir = $container->getParameter('kernel.root_dir');

        $action = $input->getArgument('action');
        $method = 'execute'.ucfirst($action);

        if (method_exists($this, $method)) {
            $this->$method($input, $output);
        } else {
            throw new \Exception(sprintf('Wrong argument, the command method "%s" does not exist', $method));
        }
    }

    /**
     * Updates the graph schema constraints.
     *
     * @param object \InputInterface
     * @param object \OutputInterface
     * @return void
     */
    private function executeUpdate(InputInterface $input, OutputInterface $output)
    {
        // retrieve all entities files using doctrine
        $cyphers = array();
        $namespaces = $this->em->getConfiguration()->getEntityNamespaces();
        $meta = $this->em->getMetadataFactory()->getAllMetadata();

        foreach ($meta as $class) {
            $namespace = $class->getName();
            $reflection = new \ReflectionClass($namespace);

            $mockupClass = $reflection->newInstance();
            $classAnnotations = GraphAnnotationReader::getClassAnnotations($mockupClass);

            // retrieve constraints annotations
            $constraints = $classAnnotations->getProperty('constraints');

            foreach ($constraints as $field) {
                $cypherA = (new Cypher())->dropConstraint('Person', 'id');
                $cypherB = (new Cypher())->createConstraint('Person', 'id');

                // just try to drop the constraint first
                $output->writeln('  - <comment>'.$cypherA->getQuery().'</comment>');
                try { $this->neo4j->transaction($cypherA); } catch (\Exception $e) { /* nothing... */ }

                // recreate the constraint
                $output->writeln('  + <comment>'.$cypherB->getQuery().'</comment>');
                $this->neo4j->transaction($cypherB);
            }
        }

        $output->writeln(sprintf('<info>Neo4j graph schema successfuly updated</info>'));
    }
    
    private function getNamespaceFromPath($filepath)
    {
        $relativePath = str_replace();
        return str_replace(array('/', '.php'), array('\\', ''), $filepath);
    }
}
