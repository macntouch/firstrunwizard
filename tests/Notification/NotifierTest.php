<?php
/**
 * @copyright Copyright (c) 2016, Joas Schilling <coding@schilljs.com>
 *
 * @author Joas Schilling <coding@schilljs.com>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\FirstRunWizard\Tests\Notification;

use OCA\FirstRunWizard\Notification\Notifier;
use OCP\IImage;
use OCP\IL10N;
use OCP\IUser;
use OCP\IUserManager;
use OCP\L10N\IFactory;
use OCP\Notification\IManager;
use OCP\Notification\INotification;
use Test\TestCase;

class NotifierTest extends TestCase {
	/** @var Notifier */
	protected $notifier;

	/** @var IManager|\PHPUnit_Framework_MockObject_MockObject */
	protected $manager;
	/** @var IUserManager|\PHPUnit_Framework_MockObject_MockObject */
	protected $userManager;
	/** @var IFactory|\PHPUnit_Framework_MockObject_MockObject */
	protected $factory;
	/** @var IL10N|\PHPUnit_Framework_MockObject_MockObject */
	protected $l;

	protected function setUp() {
		parent::setUp();

		$this->manager = $this->createMock(IManager::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->l = $this->createMock(IL10N::class);
		$this->l->expects($this->any())
			->method('t')
			->willReturnCallback(function($string, $args) {
				return vsprintf($string, $args);
			});
		$this->factory = $this->createMock(IFactory::class);
		$this->factory->expects($this->any())
			->method('get')
			->willReturn($this->l);

		$this->notifier = new Notifier(
			$this->factory,
			$this->userManager,
			$this->manager
		);
	}

	/**
	 * @expectedException \InvalidArgumentException
	 */
	public function testPrepareWrongApp() {
		/** @var INotification|\PHPUnit_Framework_MockObject_MockObject $notification */
		$notification = $this->createMock(INotification::class);
		$notification->expects($this->once())
			->method('getApp')
			->willReturn('notifications');
		$notification->expects($this->never())
			->method('getSubject');

		$this->notifier->prepare($notification, 'en');
	}

	/**
	 * @expectedException \InvalidArgumentException
	 */
	public function testPrepareWrongSubject() {
		/** @var INotification|\PHPUnit_Framework_MockObject_MockObject $notification */
		$notification = $this->createMock(INotification::class);
		$notification->expects($this->once())
			->method('getApp')
			->willReturn('firstrunwizard');
		$notification->expects($this->once())
			->method('getSubject')
			->willReturn('wrong subject');

		$this->notifier->prepare($notification, 'en');
	}

	/**
	 * @return \OCP\IUser|\PHPUnit_Framework_MockObject_Builder_InvocationMocker
	 */
	/**
	 * @param bool $changeName
	 * @param bool $changeAvatar
	 * @param string $name
	 * @param string $email
	 * @param IImage|null $avatar
	 * @return IUser|\PHPUnit_Framework_MockObject_MockObject
	 */
	protected function getUserMock($changeName, $changeAvatar, $name, $email, $avatar) {
		$user = $this->createMock(IUser::class);
		$user->expects($this->atMost(1))
			->method('canChangeDisplayName')
			->willReturn($changeName);
		$user->expects($this->atMost(1))
			->method('canChangeAvatar')
			->willReturn($changeAvatar);
		$user->expects($this->atMost(1))
			->method('getDisplayName')
			->willReturn($name);
		$user->expects($this->atMost(1))
			->method('getEMailAddress')
			->willReturn($email);
		$user->expects($this->atMost(1))
			->method('getAvatarImage')
			->willReturn($avatar);
		return $user;
	}

	public function dataPrepare() {
		return [
			['en', 'user', false, false, 'Changed Name', 'Changed Email', $this->createMock(IImage::class), true],
			['en', 'user', true, true, 'Changed Name', 'Changed Email', $this->createMock(IImage::class), true],

			['en', 'user', true, true, '', 'Changed Email', $this->createMock(IImage::class), false], // No name
			['en', 'user', false, true, '', 'Changed Email', $this->createMock(IImage::class), true], // No name - but stuck with it
			['en', 'user', true, true, 'Changed Name', '', $this->createMock(IImage::class), false], // No email
			['de', 'user2', true, true, 'Changed Name', 'Changed Email', null, false], // No avatar
			['de', 'user2', false, false, 'Changed Name', 'Changed Email', null, true], // No avatar - but stuck with it
		];
	}

	/**
	 * @dataProvider dataPrepare
	 */
	public function testPrepare($language, $user, $changeName, $changeAvatar, $name, $email, $avatar, $dismissNotification) {
		/** @var \OCP\Notification\INotification|\PHPUnit_Framework_MockObject_MockObject $notification */
		$notification = $this->getMockBuilder('OCP\Notification\INotification')
			->disableOriginalConstructor()
			->getMock();

		$notification->expects($this->once())
			->method('getApp')
			->willReturn('firstrunwizard');
		$notification->expects($this->once())
			->method('getSubject')
			->willReturn('profile');
		$notification->expects($this->once())
			->method('getUser')
			->willReturn($user);

		$this->userManager->expects($this->once())
			->method('get')
			->willReturn($this->getUserMock($changeName, $changeAvatar, $name, $email, $avatar));

		if ($dismissNotification) {
			$this->manager->expects($this->once())
				->method('markProcessed')
				->with($notification);

			$notification->expects($this->never())
				->method('setParsedSubject');

			$this->setExpectedException(\InvalidArgumentException::class);
		} else {
			$this->manager->expects($this->never())
				->method('markProcessed');

			$this->factory->expects($this->once())
				->method('get')
				->with('firstrunwizard', $language)
				->willReturn($this->l);

			$notification->expects($this->once())
				->method('setParsedSubject')
				->with('Add your profile information! :) For example your email is needed to reset your password.')
				->willReturnSelf();
		}

		$return = $this->notifier->prepare($notification, $language);
		$this->assertEquals($notification, $return);
	}
}
