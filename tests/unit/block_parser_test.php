<?php
namespace gospodinenotik\advancedhide\tests\unit;

use gospodinenotik\advancedhide\service\block_parser;

class block_parser_test extends \phpbb_test_case
{
	protected $parser;
	protected $config;

	protected function setUp(): void
	{
		parent::setUp();
		$this->config = new \phpbb\config\config([
			'advancedhide_max_blocks' => 20,
		]);
		$this->parser = new block_parser($this->config, './', 'php');
	}

	public function test_single_password_per_block_limit()
	{
		$input = '[hide=pass,pass1,pass2]Секрет[/hide]';
		$canonical = $this->parser->canonicalize_and_hash($input);
		$this->assertStringContainsString('pass_limit_exceeded', $canonical);
	}

	public function test_max_three_passwords_per_post()
	{
		$input = '[hide=pass,p1]1[/hide][hide=pass,p2]2[/hide][hide=pass,p3]3[/hide][hide=pass,p4]4[/hide]';
		$canonical = $this->parser->canonicalize_and_hash($input);
		$this->assertStringContainsString('pass_limit_exceeded', $canonical);
	}

	public function test_code_block_isolation()
	{
		$input = '[code][hide=pass,example]Пример[/hide][/code][hide=posts,10]Контент[/hide]';
		$canonical = $this->parser->canonicalize_and_hash($input);
		$this->assertStringContainsString('[code][hide=pass,example]Пример[/hide][/code]', $canonical);
	}

	public function test_negative_conditions_canonicalization()
	{
		$input = '[hide=not_groups,5;not_users,12]Контент[/hide]';
		$blocks = $this->parser->parse_blocks($input);
		$this->assertCount(1, $blocks);
		$this->assertEquals('not_groups,5;not_users,12', $blocks[0]->cond_str);
	}
}
