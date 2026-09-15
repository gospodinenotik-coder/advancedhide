<?php
namespace vendor\advancedhide\console\command;

if (!defined('IN_PHPBB'))
{
	exit;
}

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class reparse extends Command
{
	/** @var \phpbb\user */
	protected $user;

	/** @var \phpbb\db\driver\driver_interface */
	protected $db;

	/** @var \vendor\advancedhide\service\block_parser */
	protected $parser;

	/** @var string */
	protected $table_prefix;

	public function __construct(\phpbb\user $user, \phpbb\db\driver\driver_interface $db, \vendor\advancedhide\service\block_parser $parser, $table_prefix)
	{
		$this->user = $user;
		$this->db = $db;
		$this->parser = $parser;
		$this->table_prefix = $table_prefix;
		parent::__construct();
	}

	protected function configure()
	{
		$this
			->setName('advancedhide:reparse')
			->setDescription('Reparses all [hide] blocks in posts and updates legacy or plaintext passwords to secure modern hashes.')
			->addOption('batch-size', 'b', InputOption::VALUE_REQUIRED, 'Number of posts to process per batch', 200);
	}

	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$batch_size = (int) $input->getOption('batch-size');
		if ($batch_size <= 0)
		{
			$batch_size = 200;
		}

		$output->writeln('<info>AdvancedHide Content Reparser</info>');
		$output->writeln('<info>Starting AdvancedHide post reparse...</info>');

		$posts_table = $this->table_prefix . 'posts';
		$sql = 'SELECT COUNT(post_id) AS total FROM ' . $posts_table . " WHERE post_text " . $this->db->sql_like_expression($this->db->get_any_char() . '[hide' . $this->db->get_any_char());
		$result = $this->db->sql_query($sql);
		$total_posts = (int) $this->db->sql_fetchfield('total');
		$this->db->sql_freeresult($result);

		if ($total_posts === 0)
		{
			$output->writeln('<comment>No posts containing [hide] BBCode found.</comment>');
			return 0;
		}

		$output->writeln(sprintf('Found <comment>%d</comment> posts with [hide] blocks.', $total_posts));

		$processed = 0;
		$updated = 0;
		$last_post_id = 0;

		while ($processed < $total_posts)
		{
			$sql = 'SELECT post_id, post_text FROM ' . $posts_table . '
				WHERE post_id > ' . (int) $last_post_id . "
					AND post_text " . $this->db->sql_like_expression($this->db->get_any_char() . '[hide' . $this->db->get_any_char()) . '
				ORDER BY post_id ASC';
			$result = $this->db->sql_query_limit($sql, $batch_size);

			$batch_posts = [];
			while ($row = $this->db->sql_fetchrow($result))
			{
				$batch_posts[] = $row;
			}
			$this->db->sql_freeresult($result);

			if (empty($batch_posts))
			{
				break;
			}

			foreach ($batch_posts as $row)
			{
				$post_id = (int) $row['post_id'];
				$last_post_id = $post_id;
				$original_text = $row['post_text'];

				$parsed_text = $this->parser->canonicalize_and_hash($original_text);

				if ($parsed_text !== $original_text)
				{
					$sql_update = 'UPDATE ' . $posts_table . "
						SET post_text = '" . $this->db->sql_escape($parsed_text) . "',
							post_checksum = '" . $this->db->sql_escape(md5($parsed_text)) . "'
						WHERE post_id = " . $post_id;
					$this->db->sql_query($sql_update);
					$updated++;
				}

				$processed++;
			}

			$output->writeln(sprintf('Processed: %d / %d (Updated: %d)', $processed, $total_posts, $updated));
		}

		$output->writeln(sprintf('<info>[OK] Reparsing complete. Synchronized %d posts.</info>', $updated));
		$output->writeln(sprintf('<info>Done! Reparsed %d posts, updated %d posts.</info>', $processed, $updated));

		return 0;
	}
}