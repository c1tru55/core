<?php

/*
 * This file is part of the API Platform project.
 *
 * (c) Kévin Dunglas <dunglas@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ApiPlatform\Core\Tests\Metadata\Property\Factory;

use ApiPlatform\Core\Annotation\ApiProperty;
use ApiPlatform\Core\Metadata\Property\Factory\AnnotationPropertyMetadataFactory;
use ApiPlatform\Core\Tests\ProphecyTrait;
use ApiPlatform\Exception\PropertyNotFoundException;
use ApiPlatform\Metadata\ApiProperty as ApiPropertyMetadata;
use ApiPlatform\Metadata\Property\Factory\PropertyMetadataFactoryInterface;
use ApiPlatform\Tests\Fixtures\TestBundle\Entity\Dummy;
use ApiPlatform\Tests\Fixtures\TestBundle\Entity\DummyPhp8;
use Doctrine\Common\Annotations\Reader;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 * @group legacy
 */
class AnnotationPropertyMetadataFactoryTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @dataProvider dependenciesProvider
     * @group legacy
     *
     * @param mixed $reader
     * @param mixed $decorated
     */
    public function testCreateProperty($reader, $decorated, string $description)
    {
        $factory = new AnnotationPropertyMetadataFactory($reader->reveal(), $decorated ? $decorated->reveal() : null);
        $metadata = $factory->create(Dummy::class, 'name');

        $this->assertEquals($description, $metadata->getDescription());
        $this->assertTrue($metadata->isReadable());
        $this->assertTrue($metadata->isWritable());
        $this->assertFalse($metadata->isReadableLink());
        $this->assertFalse($metadata->isWritableLink());
        $this->assertFalse($metadata->isIdentifier());
        $this->assertTrue($metadata->isRequired());
        $this->assertEquals('foo', $metadata->getIri());
        $this->assertEquals(['foo' => 'bar'], $metadata->getAttributes());
    }

    /**
     * @requires PHP 8.0
     */
    public function testCreateAttribute()
    {
        $factory = new AnnotationPropertyMetadataFactory();

        $metadata = $factory->create(DummyPhp8::class, 'id');
        $this->assertTrue($metadata->isIdentifier());
        $this->assertSame('the identifier', $metadata->getDescription());

        $metadata = $factory->create(DummyPhp8::class, 'foo');
        $this->assertSame('a foo', $metadata->getDescription());
    }

    public function dependenciesProvider(): array
    {
        $annotation = new ApiProperty();
        $annotation->description = 'description';
        $annotation->readable = true;
        $annotation->writable = true;
        $annotation->readableLink = false;
        $annotation->writableLink = false;
        $annotation->identifier = false;
        $annotation->required = true;
        $annotation->iri = 'foo';
        $annotation->attributes = ['foo' => 'bar'];

        $propertyReaderProphecy = $this->prophesize(Reader::class);
        $propertyReaderProphecy->getPropertyAnnotation(Argument::type(\ReflectionProperty::class), ApiProperty::class)->willReturn($annotation)->shouldBeCalled();

        $getterReaderProphecy = $this->prophesize(Reader::class);
        $getterReaderProphecy->getPropertyAnnotation(Argument::type(\ReflectionProperty::class), ApiProperty::class)->willReturn(null)->shouldBeCalled();
        $getterReaderProphecy->getMethodAnnotation(Argument::type(\ReflectionMethod::class), ApiProperty::class)->willReturn($annotation)->shouldBeCalled();

        $setterReaderProphecy = $this->prophesize(Reader::class);
        $setterReaderProphecy->getPropertyAnnotation(Argument::type(\ReflectionProperty::class), ApiProperty::class)->willReturn(null)->shouldBeCalled();
        $setterReaderProphecy->getMethodAnnotation(Argument::type(\ReflectionMethod::class), ApiProperty::class)->willReturn(null)->shouldBeCalled();
        $setterReaderProphecy->getMethodAnnotation(Argument::type(\ReflectionMethod::class), ApiProperty::class)->willReturn($annotation)->shouldBeCalled();

        $decoratedThrowNotFoundProphecy = $this->prophesize(PropertyMetadataFactoryInterface::class);
        $decoratedThrowNotFoundProphecy->create(Dummy::class, 'name', [])->willThrow(new PropertyNotFoundException())->shouldBeCalled();

        $decoratedReturnProphecy = $this->prophesize(PropertyMetadataFactoryInterface::class);
        $decoratedReturnProphecy->create(Dummy::class, 'name', [])->willReturn((new ApiPropertyMetadata())->withDescription('hi'))->shouldBeCalled();

        return [
            [$propertyReaderProphecy, null, 'description'],
            [$getterReaderProphecy, $decoratedThrowNotFoundProphecy, 'description'],
            [$setterReaderProphecy, $decoratedThrowNotFoundProphecy, 'description'],
            [$setterReaderProphecy, $decoratedReturnProphecy, 'description'],
        ];
    }

    public function testClassNotFound()
    {
        $this->expectException(PropertyNotFoundException::class);
        $this->expectExceptionMessage('Property "foo" of class "\\DoNotExist" not found.');

        $factory = new AnnotationPropertyMetadataFactory($this->prophesize(Reader::class)->reveal());
        $factory->create('\DoNotExist', 'foo');
    }

    public function testClassNotFoundButParentFound()
    {
        $propertyMetadata = new ApiProperty();

        $decoratedProphecy = $this->prophesize(PropertyMetadataFactoryInterface::class);
        $decoratedProphecy->create('\DoNotExist', 'foo', [])->willReturn($propertyMetadata);

        $factory = new AnnotationPropertyMetadataFactory($this->prophesize(Reader::class)->reveal(), $decoratedProphecy->reveal());
        $this->assertEquals($propertyMetadata, $factory->create('\DoNotExist', 'foo'));
    }
}
