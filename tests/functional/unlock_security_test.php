<?php
namespace gospodinenotik\advancedhide\tests\functional;

class unlock_security_test extends \phpbb_functional_test_case
{
	static protected function setup_extensions()
	{
		return ['gospodinenotik/advancedhide'];
	}

	public function test_unlock_requires_csrf_token()
	{
		$crawler = $this->request('POST', 'app.php/advancedhide/unlock', [
			'post_id'  => 1,
			'block_id' => 1,
			'password' => 'test',
		], [], ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']);

		$this->assertResponseStatus(403);
	}

	public function test_rate_limiter_triggers_429()
	{
		// Проверка 429 Too Many Requests при превышении минутного бакета
		for ($i = 0; $i < 6; $i++)
		{
			$crawler = $this->request('POST', 'app.php/advancedhide/unlock', [
				'post_id'    => 1,
				'block_id'   => 1,
				'password'   => 'wrong_pass_' . $i,
				'form_token' => $this->generate_valid_token(),
			], [], ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']);
		}

		$this->assertResponseStatus(429);
	}

	protected function generate_valid_token()
	{
		$now = time();
		return sha1($now . 'test_salt' . 'advancedhide_unlock' . '');
	}
}
