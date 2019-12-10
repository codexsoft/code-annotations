<?php /** @noinspection ParameterDefaultValueIsNotNullInspection */

namespace CodexSoft\Code;

use CodexSoft\Code\Helpers\Classes;
use Doctrine\Common\Annotations\DocParser;
use CodexSoft\Code\Annotations\Lang;
use Psr\Log\LoggerInterface;

/**
 * Class Annotations
 * Хэлпер для работы с аннотациями.
 */
class Annotations
{

    /** @var DocParser */
    private static $parser;

    /** @var LoggerInterface */
    private static $logger;

    protected static function boot(): DocParser
    {

        if (!self::$parser instanceof DocParser) {

            self::$parser = new DocParser();
            self::$parser->setIgnoreNotImportedAnnotations(true);

            // preload annotation classes
            //self::$parser->addNamespace('App\Annotations');
            //\class_exists(Lang\Rus::class);
            //\class_exists(Lang\Eng::class);
        }

        return self::$parser;
    }

    public static function getCommentForAnnotatedConstantByValue(
        string $sourceClass,
        string $annotationClass,
        $constantValue,
        string $annotationProperty = null,
        string $constantPrefix = ''
    ): ?string
    {
        try {
            $parser = self::boot();

            $constants = Classes::grabPrefixedConstantsFromClass($sourceClass,$constantPrefix);
            $oClass = new \ReflectionClass($sourceClass);

            foreach ($constants as $constantName => $constantVal) {

                if ((int)$constantVal === (int)$constantValue) {
                    $refConst = $oClass->getReflectionConstant($constantName);
                    $constantAnnotations = $parser->parse($refConst->getDocComment());

                    $comment = self::getAnnotationPropertyValueFromAnnotationsArray($constantAnnotations, $annotationClass, $annotationProperty);
                    if ($comment !== null) {
                        return $comment;
                    }

                    /*
                    foreach ($constantAnnotations as $annotation) {

                        // аннотация должна быть наследником заданного класса
                        if (!Classes::getIsSameOrExtends($annotation,$annotationClass)) {
                            continue;
                        }

                        // если задано имя свойства из которого надо взять значение, берем
                        if ($annotationProperty && property_exists($annotation,$annotationProperty)) {
                            return $annotation->$annotationProperty;
                        }

                        // если имя свойства не задано, для аннотаций в которых публичное свойство
                        // только одно берем значение из такого свойства
                        if (!$annotationProperty) {
                            $singlePropertyReflection = Classes::getSinglePublicReflectionPropertyOrNull(\get_class($annotation));
                            if ($singlePropertyReflection) {
                                $annotationProperty = $singlePropertyReflection->getName();
                                return $annotation->$annotationProperty;
                            }
                        }

                        // else ignoring

                    }
                    */

                }

            }
        } catch (\Throwable $t) {
            self::$logger->warning('Failed to collect comments for constants in class '.$sourceClass);
        }

        return null;

    }

    /**
     * Парсит аннотации для констант заданного класса, ищет среди них аннотацию заданного класса,
     * и добавляет в результирующий массив элемент с ключом, равным имени константы, и значением,
     * равным значению заданного поля объекта аннотации. Например:
     *
     * class Example {
     *
     *     / ** @Annotated(prop="hello") * /
     *     public const TEST_A = 10;
     *
     *     / ** @Annotated(prop="world") * /
     *     public const TEST_B = 20;
     * }
     *
     * Annotations::collectConstantValueToComment(Example::class,'',Annotated::class,'prop') выдаст
     * массив типа
     *
     * ['TEST_A' => 'hello', 'TEST_B' => 'world']
     *
     * @param string $sourceClass в каком классе искать константы
     * @param string $constantsPrefix префикс констант (например, STATE_)
     * @param string $annotationClass в аннотации какого класса лежит нужное значение
     * @param string|null $annotationProperty в каком свойстве аннотации лежит нужное значение
     * @param bool $stripPrefix надо ли обрезать префикс в ключах результирующего массива
     *
     * todo: сделать набор fallback-аннотаций/свойств вида [AnnotationA::class => ['client','producer'], AnnotationB::class => ['client']]
     *
     * @return string[]
     */
    public static function collectConstantNameToComment(
        string $sourceClass,
        string $constantsPrefix = '',
        string $annotationClass = Lang\Rus::class,
        string $annotationProperty = null,
        bool $stripPrefix = false
    ): array
    {

        $result = [];

        try {
            $parser = self::boot();

            $constants = Classes::grabPrefixedConstantsFromClass($sourceClass,$constantsPrefix);
            $oClass = new \ReflectionClass($sourceClass);

            foreach ($constants as $constantName => $constantValue) {

                if ($stripPrefix) {
                    $name = (string) str($constantName)->removeLeft($constantsPrefix);
                } else {
                    $name = $constantName;
                }

                $refConst = $oClass->getReflectionConstant($constantName);
                $constantAnnotations = $parser->parse($refConst->getDocComment());
                $comment = self::getAnnotationPropertyValueFromAnnotationsArray($constantAnnotations, $annotationClass, $annotationProperty);
                /*
                foreach ($constantAnnotations as $annotation) {

                    // аннотация должна быть наследником заданного класса
                    if (!Classes::getIsSameOrExtends($annotation,$annotationClass)) {
                        continue;
                    }

                    // если задано имя свойства из которого надо взять значение, берем
                    if ($annotationProperty && property_exists($annotation,$annotationProperty)) {
                        $comment = $annotation->$annotationProperty;
                        break;
                    }

                    // если имя свойства не задано, для аннотаций в которых публичное свойство
                    // только одно берем значение из такого свойства
                    if (!$annotationProperty) {
                        $singlePropertyReflection = Classes::getSinglePublicReflectionPropertyOrNull(\get_class($annotation));
                        if ($singlePropertyReflection) {
                            $annotationProperty = $singlePropertyReflection->getName();
                            $comment = $annotation->$annotationProperty;
                            break;
                        }
                    }

                    // else ignoring

                }
                */

                $result[$name] = $comment;

            }
        } catch (\Throwable $t) {
            self::$logger->warning('Failed to collect comments for constants in class '.$sourceClass);
        }

        return $result;
    }

    /**
     * Парсит аннотации для констант заданного класса, ищет среди них аннотацию заданного класса,
     * и добавляет в результирующий массив элемент с ключом, равным значению константы, и значением,
     * равным значению заданного поля объекта аннотации. Например:
     *
     * class Example {
     *
     *     / ** @Annotated(prop="hello") * /
     *     public const TEST_A = 10;
     *
     *     / ** @Annotated(prop="world") * /
     *     public const TEST_B = 20;
     * }
     *
     * Annotations::collectConstantValueToComment(Example::class,'',Annotated::class,'prop') выдаст
     * массив типа
     *
     * [10 => 'hello', 20 => 'world']
     *
     * @param string $sourceClass в каком классе искать константы
     * @param string $constantsPrefix префикс констант (например, STATE_)
     * @param string $annotationClass в аннотации какого класса лежит нужное значение
     * @param string|null $annotationProperty в каком свойстве аннотации лежит нужное значение
     *
     * todo: сделать набор fallback-аннотаций/свойств вида [AnnotationA::class => ['client','producer'], AnnotationB::class => ['client']]
     *
     * @return string[]
     */
    public static function collectConstantValueToComment(
        string $sourceClass,
        string $constantsPrefix = '',
        string $annotationClass = Lang\Rus::class,
        string $annotationProperty = null
    ): array
    {

        $result = [];

        try {

            $parser = self::boot();

            $constants = Classes::grabPrefixedConstantsFromClass($sourceClass,$constantsPrefix);
            $oClass = new \ReflectionClass($sourceClass);

            foreach ($constants as $constantName => $constantValue) {

                $comment = '';
                $refConst = $oClass->getReflectionConstant($constantName);
                $constantAnnotations = $parser->parse($refConst->getDocComment());
                foreach ($constantAnnotations as $annotation) {

                    // аннотация должна быть наследником заданного класса
                    if (!Helpers\Classes::getIsSameOrExtends($annotation, $annotationClass)) {
                        continue;
                    }

                    // если задано имя свойства из которого надо взять значение, берем
                    if ($annotationProperty && property_exists($annotation,$annotationProperty)) {
                        $comment = $annotation->$annotationProperty;
                        break;
                    }

                    // если имя свойства не задано, для аннотаций в которых публичное свойство
                    // только одно берем значение из такого свойства
                    if (!$annotationProperty) {
                        $singlePropertyReflection = Classes::getSinglePublicReflectionPropertyOrNull(\get_class($annotation));
                        if ($singlePropertyReflection) {
                            $annotationProperty = $singlePropertyReflection->getName();
                            $comment = $annotation->$annotationProperty;
                            break;
                        }
                    }

                    // else ignoring

                }

                $result[$constantValue] = $comment;

            }

        } catch (\Throwable $t) {
            self::$logger->warning('Failed to collect comments for constants in class '.$sourceClass);
        }

        return $result;
    }

    /**
     * Получить Lang\Rus аннотацию для заданного значения константы из заданного класса
     * Поиск подходящей костанты осуществляется либо по всем константам класса (до первой
     * совпавшей по значению) либо (если задан префикс константы, например "STATUS_") — по всем
     * константам с заданным префиксом.
     *
     * @example $desc = Annotations::getRusAnnotation(5, SomeEntity::class, 'STATUS_');
     *
     * @param $value
     * @param string $class
     * @param string $prefix
     *
     * @return null|string
     */
    public static function getRusAnnotation($value, string $class, string $prefix): ?string
    {
        $annotation = Annotations::getCommentForAnnotatedConstantByValue(
            $class, Lang\Rus::class, $value, 'content', $prefix
        );

        if ($annotation === null) {
            $annotation = Classes::getConstantNameByValue($value, $class, $prefix);
        }
        return $annotation;
    }

    /**
     * Получить массив вида [<value> => <description>], который будет заполнен так:
     * В заданном классе выбираются все константы (опционально — только с заданным префиксом
     * в имени), и для каждой константы в результирующем массиве создается элемент, в котором
     * ключом становится значение константы, а значением — либо содержимое аннотации Lang\Rus,
     * либо, если таковая не обнаружена — имя константы.
     *
     * @param string $class
     * @param string $prefix
     *
     * @return array
     */
    public static function getRusAnnotations(string $class, string $prefix = ''): array
    {
        $result = [];
        $annotations = Annotations::collectConstantValueToComment($class,$prefix);
        foreach ($annotations as $value => $annotation) {
            if ($annotation) {
                $result[$value] = $annotation;
            } else {
                $result[$value] = Classes::getConstantNameByValue($value, $class, $prefix);
            }
        }
        return $result;
    }

    /**
     * Получить массив, вида [<value> => <description>], который будет заполнен так:
     * В заданном классе выбираются все константы (опционально — только с заданным префиксом
     * в имени), и для каждой константы в результирующем массиве создается элемент, в котором
     * ключом становится значение константы, а значением — либо содержимое аннотации Lang\Rus,
     * либо, если таковая не обнаружена — имя константы. В отличие от getRusAnnotations,
     * дополнительным условием попадания записи в результирующий массив является вхождение значения
     * константы в набор заданных значений $valueArray.
     *
     * @param array $valueArray
     * @param $class
     * @param $prefix
     *
     * @return array
     */
    public static function describeValuesUsingAnnotations(array $valueArray, string $class, string $prefix = ''): array
    {
        $annotations = self::getRusAnnotations($class,$prefix);
        return array_filter($annotations, function($k) use ($valueArray) {
            return \in_array($k,$valueArray,true);
        }, ARRAY_FILTER_USE_KEY);
    }

    /** @noinspection MoreThanThreeArgumentsInspection */
    /**
     * Получить значение заданного свойства заданной аннотации заданного класса.
     * Если не удалось ничего найти, вернет null.
     *
     * @param string $class
     * @param string $annotationClass
     * @param string|null $annotationProperty
     *
     * @return mixed
     * @throws \ReflectionException
     */
    public static function getAnnotationPropertyValueOfClass(string $class,string $annotationClass,string $annotationProperty = null)
    {
        $parser = self::boot();
        $refClass = new \ReflectionClass($class);
        $methodDocBlock = $refClass->getDocComment();
        $annotations = $parser->parse($methodDocBlock);
        $comment = self::getAnnotationPropertyValueFromAnnotationsArray($annotations,$annotationClass,$annotationProperty);
        return $comment;
    }

    /** @noinspection MoreThanThreeArgumentsInspection */
    /**
     * Получить значение заданного свойства заданной аннотации в заданном методе заданного класса.
     * Если не удалось ничего найти, вернет null.
     *
     * @param string $class
     * @param string $method
     * @param string $annotationClass
     * @param string|null $annotationProperty
     *
     * @return mixed
     * @throws \ReflectionException
     */
    public static function getAnnotationPropertyValueOfClassMethod(string $class,string $method,string $annotationClass,string $annotationProperty = null)
    {
        $parser = self::boot();
        $refClass = new \ReflectionClass($class);
        $refMethod = $refClass->getMethod($method);
        $methodDocBlock = $refMethod->getDocComment();
        $annotations = $parser->parse($methodDocBlock);
        $comment = self::getAnnotationPropertyValueFromAnnotationsArray($annotations,$annotationClass,$annotationProperty);
        return $comment;
    }

    /**
     * reusable piece of code
     *
     * @param $annotations
     * @param string $annotationClass
     * @param string|null $annotationProperty
     *
     * @return null
     */
    protected static function getAnnotationPropertyValueFromAnnotationsArray(array $annotations,string $annotationClass,string $annotationProperty = null)
    {
        $value = null;
        foreach ($annotations as $annotation) {

            // аннотация должна быть наследником заданного класса
            if (!Helpers\Classes::getIsSameOrExtends($annotation, $annotationClass)) {
                continue;
            }

            // если задано имя свойства из которого надо взять значение, берем
            if ($annotationProperty && property_exists($annotation,$annotationProperty)) {
                /** @noinspection PhpVariableVariableInspection */
                $value = $annotation->$annotationProperty;
                break;
            }

            // если имя свойства не задано, для аннотаций в которых публичное свойство
            // только одно берем значение из такого свойства
            if (!$annotationProperty) {
                $singlePropertyReflection = Classes::getSinglePublicReflectionPropertyOrNull(\get_class($annotation));
                if ($singlePropertyReflection) {
                    $annotationProperty = $singlePropertyReflection->getName();
                    /** @noinspection PhpVariableVariableInspection */
                    $value = $annotation->$annotationProperty;
                    break;
                }
            }

            // else ignoring

        }
        return $value;
    }

    /**
     * Получить объект аннотации заданного класса для заданного метода заданного класса
     *
     * @param object[] $annotations
     * @param string $annotationClass
     *
     * @return null|object
     */
    protected static function getAnnotationFromAnnotationsArray(array $annotations,string $annotationClass)
    {
        foreach ($annotations as $annotation) {

            // аннотация должна быть наследником заданного класса
            if (!Helpers\Classes::getIsSameOrExtends($annotation, $annotationClass)) {
                continue;
            }

            return $annotation;

        }
        return null;
    }

    /**
     * Получить объект аннотации заданного класса для заданного метода заданного класса
     *
     * @param string $class
     * @param string $method
     * @param string $annotationClass
     *
     * @return null|object
     * @throws \ReflectionException
     */
    public static function getAnnotationOfClassMethod(string $class,string $method,string $annotationClass)
    {
        $parser = self::boot();
        $refClass = new \ReflectionClass($class);
        $refMethod = $refClass->getMethod($method);
        $methodDocBlock = $refMethod->getDocComment();
        $annotations = $parser->parse($methodDocBlock);
        return self::getAnnotationFromAnnotationsArray($annotations,$annotationClass);
    }

    /**
     * Получить объект аннотации заданного класса для заданного свойства заданного класса
     *
     * @param string $class
     * @param string $property
     * @param string $annotationClass
     *
     * @return null|object
     * @throws \ReflectionException
     */
    public static function getAnnotationOfClassProperty(string $class,string $property,string $annotationClass)
    {
        $parser = self::boot();
        $refClass = new \ReflectionClass($class);
        $refMethod = $refClass->getProperty($property);
        $propertyDocBlock = $refMethod->getDocComment();
        $annotations = $parser->parse($propertyDocBlock);
        return self::getAnnotationFromAnnotationsArray($annotations,$annotationClass);
    }

    /**
     * Получить объект аннотации заданного класса для заданной константы заданного класса
     *
     * @param string $class
     * @param string $constant
     * @param string $annotationClass
     *
     * @return null|object
     * @throws \ReflectionException
     */
    public static function getAnnotationOfClassConstant(string $class,string $constant,string $annotationClass)
    {
        $parser = self::boot();
        $refClass = new \ReflectionClass($class);
        $refMethod = $refClass->getConstant($constant);
        $constantDocBlock = $refMethod->getDocComment();
        $annotations = $parser->parse($constantDocBlock);
        return self::getAnnotationFromAnnotationsArray($annotations,$annotationClass);
    }

    /**
     * @return array
     * Usage example self::$parser->setIgnoredAnnotationNames($this->getIgnoredAnnotationNames());
     * alternative: self::$parser->setIgnoreNotImportedAnnotations(true);
     * copied from \Doctrine\Common\Annotations\AnnotationReader::$globalIgnoredNames
     */
    protected function getIgnoredAnnotationNames()
    {
        return [
            // Annotation tags
            'Annotation' => true, 'Attribute' => true, 'Attributes' => true,
            /* Can we enable this? 'Enum' => true, */
            'Required' => true,
            'Target' => true,
            // Widely used tags (but not existent in phpdoc)
            'fix' => true , 'fixme' => true,
            'override' => true,
            // PHPDocumentor 1 tags
            'abstract'=> true, 'access'=> true,
            'code' => true,
            'deprec'=> true,
            'endcode' => true, 'exception'=> true,
            'final'=> true,
            'ingroup' => true, 'inheritdoc'=> true, 'inheritDoc'=> true,
            'magic' => true,
            'name'=> true,
            'toc' => true, 'tutorial'=> true,
            'private' => true,
            'static'=> true, 'staticvar'=> true, 'staticVar'=> true,
            'throw' => true,
            // PHPDocumentor 2 tags.
            'api' => true, 'author'=> true,
            'category'=> true, 'copyright'=> true,
            'deprecated'=> true,
            'example'=> true,
            'filesource'=> true,
            'global'=> true,
            'ignore'=> true, /* Can we enable this? 'index' => true, */ 'internal'=> true,
            'license'=> true, 'link'=> true,
            'method' => true,
            'package'=> true, 'param'=> true, 'property' => true, 'property-read' => true, 'property-write' => true,
            'return'=> true,
            'see'=> true, 'since'=> true, 'source' => true, 'subpackage'=> true,
            'throws'=> true, 'todo'=> true, 'TODO'=> true,
            'usedby'=> true, 'uses' => true,
            'var'=> true, 'version'=> true,
            // PHPUnit tags
            'codeCoverageIgnore' => true, 'codeCoverageIgnoreStart' => true, 'codeCoverageIgnoreEnd' => true,
            // PHPCheckStyle
            'SuppressWarnings' => true,
            // PHPStorm
            'noinspection' => true,
            // PEAR
            'package_version' => true,
            // PlantUML
            'startuml' => true, 'enduml' => true,
            // Symfony 3.3 Cache Adapter
            'experimental' => true
        ];
    }

}